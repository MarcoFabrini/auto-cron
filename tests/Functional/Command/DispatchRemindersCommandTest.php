<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Enum\ReminderUrgency;
use App\Message\SendReminderNotificationMessage;
use App\Tests\Factory\RefuelingFactory;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\VehicleFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Il job accoda un messaggio solo per i promemoria che hanno raggiunto un NUOVO livello di
 * urgenza, sia a data sia a chilometri (prima i promemoria a km non venivano mai notificati).
 */
final class DispatchRemindersCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    /** @return list<int> id dei promemoria per cui è stato accodato un messaggio */
    private function dispatchedIds(bool $dryRun = false): array
    {
        $application = new Application(self::bootKernel());
        $tester = new CommandTester($application->find('app:reminders:dispatch'));
        $tester->execute($dryRun ? ['--dry-run' => true] : []);
        self::assertSame(Command::SUCCESS, $tester->getStatusCode());

        /** @var InMemoryTransport $transport */
        $transport = static::getContainer()->get('messenger.transport.async');
        $ids = [];
        foreach ($transport->getSent() as $envelope) {
            $message = $envelope->getMessage();
            self::assertInstanceOf(SendReminderNotificationMessage::class, $message);
            $ids[] = $message->reminderId;
        }
        $transport->reset();

        return $ids;
    }

    public function testDispatchesDateAndKmRemindersButNotTheOnesFarAway(): void
    {
        $nearVehicle = VehicleFactory::createOne(['initialKm' => 99_600]);
        $farVehicle = VehicleFactory::createOne(['initialKm' => 10_000]);

        $byDate = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+5 days'), 'notifyDaysBefore' => 7]);
        $byKm = ReminderFactory::createOne([
            'vehicle' => $nearVehicle, 'organization' => $nearVehicle->getOrganization(),
            'dueDate' => null, 'dueKm' => 100_000,
        ]);
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+90 days'), 'notifyDaysBefore' => 7]);
        ReminderFactory::createOne([
            'vehicle' => $farVehicle, 'organization' => $farVehicle->getOrganization(),
            'dueDate' => null, 'dueKm' => 100_000,
        ]);
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+1 day'), 'completedAt' => new \DateTimeImmutable()]);

        self::assertEqualsCanonicalizing([$byDate->getId(), $byKm->getId()], $this->dispatchedIds());
    }

    public function testOverdueByDateIsNotifiedToo(): void
    {
        // Prima la finestra era "da oggi in poi": un promemoria già scaduto non partiva mai.
        $overdue = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('-2 days')]);

        self::assertSame([$overdue->getId()], $this->dispatchedIds());
    }

    public function testRespectsPerReminderNotifyDaysBefore(): void
    {
        // Prima contava una finestra fissa di 30 giorni, qualunque fosse notifyDaysBefore.
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+20 days'), 'notifyDaysBefore' => 3]);
        $inWindow = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+2 days'), 'notifyDaysBefore' => 3]);

        self::assertSame([$inWindow->getId()], $this->dispatchedIds());
    }

    public function testDoesNotRepeatTheSameLevelButEscalatesToOverdue(): void
    {
        $vehicle = VehicleFactory::createOne(['initialKm' => 99_500]);
        $r = ReminderFactory::createOne([
            'vehicle' => $vehicle, 'organization' => $vehicle->getOrganization(),
            'dueDate' => null, 'dueKm' => 100_000,
        ]);

        // Notificato a livello "in scadenza": il giorno dopo il cron non lo ripropone...
        $r->markNotified(ReminderUrgency::SOON);
        static::getContainer()->get(EntityManagerInterface::class)->flush();
        self::assertSame([], $this->dispatchedIds());

        // ...ma quando i km superano la soglia sale a "scaduto" e parte la seconda notifica.
        RefuelingFactory::createOne(['vehicle' => $vehicle, 'organization' => $vehicle->getOrganization(), 'km' => 100_200]);
        self::assertSame([$r->getId()], $this->dispatchedIds());
    }

    public function testDryRunListsButDispatchesNothing(): void
    {
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+1 day'), 'notifyDaysBefore' => 7]);

        self::assertSame([], $this->dispatchedIds(dryRun: true));
    }
}
