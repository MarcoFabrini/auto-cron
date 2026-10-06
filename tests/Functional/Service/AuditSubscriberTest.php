<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\AuditLog;
use App\Entity\Reminder;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\AuditAction;
use App\Enum\FuelType;
use App\Enum\ShareRole;
use App\Enum\ReminderType;
use App\Enum\VehicleType;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test audit log Doctrine subscriber (#3.6).
 */
final class AuditSubscriberTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testInsertCreatesAuditLogEntry(): void
    {
        $org = OrganizationFactory::createOne();
        $em = $this->em();

        $v = new Vehicle();
        $v->setOrganization($org)
            ->setName('Audited')
            ->setBrand('Fiat')
            ->setModel('Panda')
            ->setYear(2020)
            ->setType(VehicleType::CAR)
            ->setFuelType(FuelType::GASOLINE)
            ->setInitialKm(0);
        $em->persist($v);
        $em->flush();

        $em->clear();
        $logs = $em->getRepository(AuditLog::class)->findBy(['entityClass' => 'Vehicle']);
        self::assertCount(1, $logs);
        self::assertSame(AuditAction::CREATED, $logs[0]->getAction());
        self::assertSame((string) $v->getId(), $logs[0]->getEntityId());
    }

    public function testUpdateCreatesAuditLogWithDiff(): void
    {
        $org = OrganizationFactory::createOne();
        $vehicle = VehicleFactory::createOne(['organization' => $org, 'name' => 'Original']);
        $em = $this->em();

        // Clear creation log to focus on update
        $em->createQuery('DELETE FROM '.AuditLog::class)->execute();

        $managed = $em->find(Vehicle::class, $vehicle->getId());
        self::assertNotNull($managed);
        $managed->setName('Renamed');
        $em->flush();

        $em->clear();
        $logs = $em->getRepository(AuditLog::class)->findBy(['action' => AuditAction::UPDATED]);
        self::assertCount(1, $logs);
        $changes = $logs[0]->getChanges();
        self::assertNotNull($changes);
        self::assertArrayHasKey('name', $changes);
        self::assertSame(['Original', 'Renamed'], $changes['name']);
    }

    public function testDeleteCreatesAuditLog(): void
    {
        $org = OrganizationFactory::createOne();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $em = $this->em();

        $em->createQuery('DELETE FROM '.AuditLog::class)->execute();

        $managed = $em->find(Vehicle::class, $vehicle->getId());
        self::assertNotNull($managed);
        $em->remove($managed);
        $em->flush();

        $em->clear();
        $logs = $em->getRepository(AuditLog::class)->findBy(['action' => AuditAction::DELETED]);
        self::assertCount(1, $logs);
    }

    public function testAllInsertsProduceCreatedLog(): void
    {
        // Sanity check: insertions of tracked entities generate CREATED logs;
        // none generate UPDATED logs (no updatedAt-only diff).
        OrganizationFactory::createOne();
        $em = $this->em();

        $em->clear();
        $logs = $em->getRepository(AuditLog::class)->findAll();
        self::assertNotEmpty($logs);
        foreach ($logs as $log) {
            self::assertSame(AuditAction::CREATED, $log->getAction());
        }
    }

    public function testDatesAndEnumsAreStoredAsPlainScalarsInTheDiff(): void
    {
        $reminder = ReminderFactory::createOne([
            'dueDate' => new \DateTimeImmutable('2026-12-31 00:00:00'),
            'type' => ReminderType::INSPECTION,
        ]);
        $em = $this->em();
        $em->createQuery('DELETE FROM '.AuditLog::class)->execute();

        $managed = $em->find(Reminder::class, $reminder->getId());
        self::assertNotNull($managed);
        $managed->setDueDate(new \DateTimeImmutable('2027-06-30 00:00:00'))->setType(ReminderType::INSURANCE);
        $em->flush();

        $em->clear();
        $logs = $em->getRepository(AuditLog::class)->findBy(['action' => AuditAction::UPDATED]);
        self::assertCount(1, $logs);
        $changes = $logs[0]->getChanges();
        self::assertNotNull($changes);
        // Il JSON del log non deve contenere oggetti serializzati: date ISO 8601 ed enum come valore
        self::assertSame(['2026-12-31T00:00:00+00:00', '2027-06-30T00:00:00+00:00'], $changes['dueDate']);
        self::assertSame(['inspection', 'insurance'], $changes['type']);
    }

    public function testAnUpdateTouchingOnlyNoiseFieldsLeavesNoAuditRow(): void
    {
        // lastNotifiedAt cambia a ogni notifica: registrarlo riempirebbe il log di righe senza informazione
        $reminder = ReminderFactory::createOne();
        $em = $this->em();
        $em->createQuery('DELETE FROM '.AuditLog::class)->execute();

        $managed = $em->find(Reminder::class, $reminder->getId());
        self::assertNotNull($managed);
        (new \ReflectionProperty(Reminder::class, 'lastNotifiedAt'))->setValue($managed, new \DateTimeImmutable('2026-01-01'));
        $em->flush();

        $em->clear();
        self::assertSame([], $em->getRepository(AuditLog::class)->findAll());
    }

    public function testVehicleShareRowsCarryTheOrganizationOfTheirVehicle(): void
    {
        // Lo share non ha un'organizzazione propria: senza risoluzione tramite il veicolo le righe
        // avrebbero organization_id NULL e GET /api/audit-logs non le mostrerebbe mai.
        $org = OrganizationFactory::createOne();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $user = UserFactory::createOne();
        $em = $this->em();
        $em->createQuery('DELETE FROM '.AuditLog::class)->execute();

        // creazione
        $share = VehicleShareFactory::createOne(['vehicle' => $vehicle, 'user' => $user]);
        $created = $em->getRepository(AuditLog::class)->findBy(['entityClass' => 'VehicleShare', 'action' => AuditAction::CREATED]);
        self::assertCount(1, $created);
        self::assertSame($org->getId(), $created[0]->getOrganization()?->getId());

        // aggiornamento
        $managed = $em->find(VehicleShare::class, $share->getId());
        self::assertNotNull($managed);
        $managed->setRole(ShareRole::ADMIN);
        $em->flush();
        $updated = $em->getRepository(AuditLog::class)->findBy(['entityClass' => 'VehicleShare', 'action' => AuditAction::UPDATED]);
        self::assertCount(1, $updated);
        self::assertSame($org->getId(), $updated[0]->getOrganization()?->getId());
        self::assertSame(['role' => ['viewer', 'admin']], $updated[0]->getChanges());

        // eliminazione
        $em->remove($managed);
        $em->flush();
        $deleted = $em->getRepository(AuditLog::class)->findBy(['entityClass' => 'VehicleShare', 'action' => AuditAction::DELETED]);
        self::assertCount(1, $deleted);
        self::assertSame($org->getId(), $deleted[0]->getOrganization()?->getId());
    }
}
