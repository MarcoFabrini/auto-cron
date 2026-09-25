<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\Reminder;
use App\Enum\ReminderUrgency;
use App\Repository\ReminderRepository;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\VehicleFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Candidati al job di notifica: la query filtra solo ciò che sa (attivo, ha una scadenza,
 * non già al livello massimo); se notificare davvero lo decide Reminder::urgency().
 */
final class ReminderRepositoryTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private ReminderRepository $repo;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->repo = static::getContainer()->get(ReminderRepository::class);
    }

    /** @return list<int|null> */
    private function candidateIds(): array
    {
        return array_map(static fn (Reminder $r) => $r->getId(), $this->repo->findNotificationCandidates());
    }

    public function testIncludesDateOnlyAndKmOnlyReminders(): void
    {
        $byDate = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+10 days'), 'dueKm' => null]);
        $byKm = ReminderFactory::createOne(['dueDate' => null, 'dueKm' => 120_000]);

        self::assertEqualsCanonicalizing([$byDate->getId(), $byKm->getId()], $this->candidateIds());
    }

    public function testExcludesCompleted(): void
    {
        ReminderFactory::createOne(['completedAt' => new \DateTimeImmutable()]);

        self::assertSame([], $this->candidateIds());
    }

    public function testExcludesReminderWithoutAnyDeadline(): void
    {
        ReminderFactory::createOne(['dueDate' => null, 'dueKm' => null]);

        self::assertSame([], $this->candidateIds());
    }

    public function testKeepsSoonNotifiedButDropsOverdueNotified(): void
    {
        $soonNotified = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+3 days')]);
        $soonNotified->markNotified(ReminderUrgency::SOON);
        $overdueNotified = ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('-3 days')]);
        $overdueNotified->markNotified(ReminderUrgency::OVERDUE);
        static::getContainer()->get(EntityManagerInterface::class)->flush();

        // "soon" può ancora salire a "overdue"; "overdue" è il livello massimo, nulla da notificare.
        self::assertSame([$soonNotified->getId()], $this->candidateIds());
    }

    /**
     * N+1: il job legge il veicolo di ogni promemoria (nome, km attuali). Senza fetch-join ogni
     * accesso è una query (30 promemoria = 30 query extra). Verifica che il vehicle arrivi già
     * caricato, non come proxy lazy — via Doctrine\Persistence\Proxy::__isInitialized(), l'unico
     * controllo che non dipende dal meccanismo di lazy-loading interno (proxy classica vs lazy
     * ghost object nativo PHP 8.4+).
     */
    public function testFetchJoinsVehicleToAvoidN1(): void
    {
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+10 days')]);

        // Scarica l'identity map: senza, il Vehicle creato dalla factory resterebbe già in
        // memoria e il test passerebbe a prescindere dal join.
        static::getContainer()->get(EntityManagerInterface::class)->clear();

        [$result] = $this->repo->findNotificationCandidates();
        $vehicle = $result->getVehicle();

        $stillLazy = $vehicle instanceof \Doctrine\Persistence\Proxy && !$vehicle->__isInitialized();
        self::assertFalse($stillLazy, 'Vehicle deve arrivare già caricato dal fetch-join, non lazy (N+1)');
    }

    public function testFindUpcomingForOrganizationOrdersByDueDateAndRespectsWindow(): void
    {
        $vehicle = VehicleFactory::createOne();
        $org = $vehicle->getOrganization();

        $far = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+60 days'), // fuori dalla finestra di 30 giorni
        ]);
        $overdue = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('-3 days'), // scaduto: va comunque incluso
        ]);
        $soon = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $result = $this->repo->findUpcomingForOrganization($org, 30, 10);

        self::assertSame(
            [$overdue->getId(), $soon->getId()],
            array_map(static fn (Reminder $r) => $r->getId(), $result),
        );
        self::assertNotContains($far->getId(), array_map(static fn (Reminder $r) => $r->getId(), $result));
    }

    public function testFindUpcomingForOrganizationExcludesOtherOrgsCompletedAndKmOnly(): void
    {
        $vehicle = VehicleFactory::createOne();
        $org = $vehicle->getOrganization();

        $mine = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        // Altra organizzazione: non deve comparire.
        ReminderFactory::createOne(['dueDate' => new \DateTimeImmutable('+5 days')]);
        // Completato: escluso.
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
            'completedAt' => new \DateTimeImmutable(),
        ]);
        // Solo a km, nessuna data: escluso da questa query (aggregazione solo su data).
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => null, 'dueKm' => 120_000,
        ]);

        $result = $this->repo->findUpcomingForOrganization($org, 30, 10);

        self::assertSame([$mine->getId()], array_map(static fn (Reminder $r) => $r->getId(), $result));
    }

    public function testFindUpcomingForOrganizationRespectsLimit(): void
    {
        $vehicle = VehicleFactory::createOne();
        $org = $vehicle->getOrganization();

        for ($i = 1; $i <= 3; $i++) {
            ReminderFactory::createOne([
                'organization' => $org, 'vehicle' => $vehicle,
                'dueDate' => new \DateTimeImmutable("+{$i} days"),
            ]);
        }

        self::assertCount(2, $this->repo->findUpcomingForOrganization($org, 30, 2));
    }
}
