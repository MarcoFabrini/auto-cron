<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Reminder;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ReminderUrgency;
use App\Enum\ShareRole;
use App\Service\AppClock;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reminder>
 */
class ReminderRepository extends ServiceEntityRepository
{
    /** Tetto di sicurezza alle liste non paginate (un veicolo ha di norma poche decine di promemoria). */
    private const MAX_LIST_RESULTS = 500;

    public function __construct(ManagerRegistry $registry, private readonly AppClock $clock)
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
            ->orderBy('r.dueDate', 'ASC')
            ->addOrderBy('r.id', 'ASC')
            ->setMaxResults(self::MAX_LIST_RESULTS);

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
     * Solo i veicoli di cui `$owner` è proprietario (share `admin` accettato): né quelli
     * condivisi con lui né, per owner/admin dell'org, quelli degli altri membri.
     *
     * @return list<Reminder>
     */
    public function findUpcomingForOwner(Organization $org, User $owner, int $days, int $limit): array
    {
        $cutoff = $this->clock->today()->modify(sprintf('+%d days', $days));

        return $this->createQueryBuilder('r')
            ->addSelect('vehicle')
            ->innerJoin('r.vehicle', 'vehicle')
            ->innerJoin('vehicle.shares', 'vs', 'WITH', 'vs.user = :owner AND vs.role = :ownerRole AND vs.acceptedAt IS NOT NULL')
            ->where('r.organization = :org')
            ->andWhere('r.completedAt IS NULL')
            ->andWhere('r.dueDate IS NOT NULL')
            ->andWhere('r.dueDate <= :cutoff')
            ->andWhere('vehicle.archivedAt IS NULL')
            ->setParameter('org', $org)
            ->setParameter('owner', $owner)
            ->setParameter('ownerRole', ShareRole::ADMIN)
            ->setParameter('cutoff', $cutoff)
            ->orderBy('r.dueDate', 'ASC')
            ->addOrderBy('r.id', 'ASC')
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
            ->andWhere('vehicle.archivedAt IS NULL')
            ->andWhere('r.dueDate IS NOT NULL OR r.dueKm IS NOT NULL')
            ->andWhere('r.notifiedUrgency IS NULL OR r.notifiedUrgency <> :max')
            ->setParameter('max', ReminderUrgency::OVERDUE)
            ->orderBy('r.dueDate', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Compare-and-swap sul livello notificato: true solo per il worker che passa per primo da
     * `$previous` a `$urgency`. Due worker sullo stesso promemoria non possono notificare due volte.
     */
    public function claimNotification(int $id, ReminderUrgency $urgency, ?ReminderUrgency $previous): bool
    {
        return $this->swapNotifiedUrgency($id, $previous, $urgency, new \DateTimeImmutable());
    }

    /**
     * Annulla un claim quando l'invio è fallito del tutto, così il retry può riprovare.
     * Ripristina anche `lastNotifiedAt`: nessuno è stato notificato.
     */
    public function releaseNotification(int $id, ReminderUrgency $claimed, ?ReminderUrgency $previous, ?\DateTimeImmutable $previousNotifiedAt = null): void
    {
        $this->swapNotifiedUrgency($id, $claimed, $previous, $previousNotifiedAt);
    }

    private function swapNotifiedUrgency(int $id, ?ReminderUrgency $from, ?ReminderUrgency $to, ?\DateTimeImmutable $notifiedAt): bool
    {
        $qb = $this->getEntityManager()->createQueryBuilder()
            ->update(Reminder::class, 'r')
            ->set('r.notifiedUrgency', ':to')
            ->set('r.lastNotifiedAt', ':notifiedAt')
            ->where('r.id = :id')
            ->setParameter('id', $id)
            ->setParameter('to', $to)
            ->setParameter('notifiedAt', $notifiedAt);

        if ($from === null) {
            $qb->andWhere('r.notifiedUrgency IS NULL');
        } else {
            $qb->andWhere('r.notifiedUrgency = :from')->setParameter('from', $from);
        }

        return $qb->getQuery()->execute() === 1;
    }
}
