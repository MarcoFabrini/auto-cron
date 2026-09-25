<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\AuditLog;
use App\Entity\Vehicle;
use App\Enum\AuditAction;
use App\Enum\FuelType;
use App\Enum\VehicleType;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\VehicleFactory;
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
}
