<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Reminder;
use App\Entity\Vehicle;
use App\Enum\ReminderUrgency;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reminder::class);
    }

    public function findOneInOrganization(int $id, Organization $org): ?Reminder
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /** @return list<Reminder> */
    public function findByVehicle(Vehicle $vehicle, bool $onlyActive = true): array
    {
        $qb = $this->createQueryBuilder('r')
            ->where('r.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('r.dueDate', 'ASC');

        if ($onlyActive) {
            $qb->andWhere('r.completedAt IS NULL');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Scadenze imminenti per la dashboard: promemoria attivi con scadenza a data entro
     * `$days` giorni da oggi, incluso ciò che è già scaduto (utile mostrarlo comunque).
     * Solo scadenze a data: quelle a km richiederebbero il chilometraggio attuale di ogni
     * veicolo per essere valutate (vedi {@see Reminder::urgency()}), troppo costoso per
     * un'aggregazione multi-veicolo leggera come questa.
     *
     * @return list<Reminder>
     */
    public function findUpcomingForOrganization(Organization $org, int $days, int $limit): array
    {
        $cutoff = (new \DateTimeImmutable('today'))->modify(sprintf('+%d days', $days));

        return $this->createQueryBuilder('r')
            ->addSelect('vehicle')
            ->innerJoin('r.vehicle', 'vehicle')
            ->where('r.organization = :org')
            ->andWhere('r.completedAt IS NULL')
            ->andWhere('r.dueDate IS NOT NULL')
            ->andWhere('r.dueDate <= :cutoff')
            ->setParameter('org', $org)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('r.dueDate', 'ASC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * Candidati al job di notifica: promemoria attivi con almeno una scadenza (data o km) e
     * per cui non è già partita la notifica del livello massimo ("scaduto"). Se serva davvero
     * notificare lo decide {@see Reminder::urgency()} + {@see Reminder::needsNotification()}:
     * dipende dai km attuali, che una query sui promemoria non conosce.
     *
     * @return list<Reminder>
     */
    public function findNotificationCandidates(): array
    {
        return $this->createQueryBuilder('r')
            ->addSelect('vehicle')
            ->innerJoin('r.vehicle', 'vehicle')
            ->where('r.completedAt IS NULL')
            ->andWhere('r.dueDate IS NOT NULL OR r.dueKm IS NOT NULL')
            ->andWhere('r.notifiedUrgency IS NULL OR r.notifiedUrgency <> :max')
            ->setParameter('max', ReminderUrgency::OVERDUE)
            ->orderBy('r.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
