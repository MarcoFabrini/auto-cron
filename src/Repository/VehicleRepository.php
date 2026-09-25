<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Vehicle>
 */
class VehicleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vehicle::class);
    }

    /**
     * Veicoli a cui l'utente ha accesso dentro l'org attiva:
     * - se l'utente è org owner/admin → tutti i veicoli dell'org
     * - se è member → solo quelli con uno share esplicito
     *
     * @return list<Vehicle>
     */
    public function findAccessibleByUserInOrganization(
        User $user,
        Organization $org,
        bool $includeArchived = false,
        bool $isOrgAdmin = false,
    ): array {
        $qb = $this->createQueryBuilder('v')
            ->where('v.organization = :org')
            ->setParameter('org', $org)
            ->orderBy('v.name', 'ASC');

        if (!$includeArchived) {
            $qb->andWhere('v.archivedAt IS NULL');
        }

        if (!$isOrgAdmin) {
            $qb->innerJoin('v.shares', 'vs', 'WITH', 'vs.user = :user AND vs.acceptedAt IS NOT NULL')
                ->setParameter('user', $user);
        }

        return $qb->getQuery()->getResult();
    }

    public function findOneInOrganization(int $id, Organization $org): ?Vehicle
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /**
     * Conta veicoli non archiviati per quota tier limits (#5.2).
     * Esclude archivedAt IS NOT NULL così l'utente può archiviare per liberare slot.
     */
    public function countActiveByOrganization(Organization $org): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(v.id)')
            ->where('v.organization = :org')
            ->andWhere('v.archivedAt IS NULL')
            ->setParameter('org', $org)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
