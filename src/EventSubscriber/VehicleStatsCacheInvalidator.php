<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Entity\Expense;
use App\Entity\Maintenance;
use App\Entity\Refueling;
use App\Entity\Vehicle;
use App\Service\VehicleStatsService;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Doctrine\ORM\Event\PostFlushEventArgs;
use Doctrine\ORM\Events;

/**
 * Le statistiche del veicolo sono in cache (5 minuti): ogni scrittura che le alimenta — rifornimenti,
 * manutenzioni, spese e il veicolo stesso (km iniziali, carburanti) — la invalida subito, così una
 * modifica si vede al prossimo caricamento invece che dopo la scadenza.
 */
#[AsDoctrineListener(event: Events::onFlush)]
#[AsDoctrineListener(event: Events::postFlush)]
final class VehicleStatsCacheInvalidator
{
    /** @var array<int, true> */
    private array $vehicleIds = [];

    public function __construct(private readonly VehicleStatsService $stats)
    {
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $uow = $args->getObjectManager()->getUnitOfWork();

        foreach ([$uow->getScheduledEntityInsertions(), $uow->getScheduledEntityUpdates(), $uow->getScheduledEntityDeletions()] as $entities) {
            foreach ($entities as $entity) {
                $vehicleId = match (true) {
                    $entity instanceof Vehicle => $entity->getId(),
                    $entity instanceof Refueling, $entity instanceof Maintenance, $entity instanceof Expense => $entity->getVehicle()->getId(),
                    default => null,
                };
                if ($vehicleId !== null) {
                    $this->vehicleIds[$vehicleId] = true;
                }
            }
        }
    }

    public function postFlush(PostFlushEventArgs $args): void
    {
        // Dopo il commit: invalidando prima, una lettura concorrente rimetterebbe in cache il dato vecchio.
        $ids = array_keys($this->vehicleIds);
        $this->vehicleIds = [];
        foreach ($ids as $id) {
            $this->stats->invalidate($id);
        }
    }
}
