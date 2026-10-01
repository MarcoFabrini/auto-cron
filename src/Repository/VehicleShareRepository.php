<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\ShareRole;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<VehicleShare>
 */
class VehicleShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, VehicleShare::class);
    }

    public function findForUserAndVehicle(User $user, Vehicle $vehicle): ?VehicleShare
    {
        return $this->findOneBy(['user' => $user, 'vehicle' => $vehicle]);
    }

    /** @return list<VehicleShare> */
    public function findByVehicle(Vehicle $vehicle): array
    {
        return $this->findBy(['vehicle' => $vehicle], ['createdAt' => 'ASC']);
    }

    /**
     * Ruolo delle condivisioni accettate dell'utente sui veicoli dell'org, in UNA query: serve
     * alle liste per sapere quali veicoli sono suoi (`admin`) e quali condivisi con lui.
     *
     * @return array<int, ShareRole> vehicleId => ruolo
     */
    public function findAcceptedRolesByUserInOrganization(User $user, Organization $org): array
    {
        /** @var list<array{vehicleId: int|string, role: ShareRole|string}> $rows */
        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.vehicle) AS vehicleId', 's.role AS role')
            ->innerJoin('s.vehicle', 'v')
            ->where('s.user = :user')
            ->andWhere('s.acceptedAt IS NOT NULL')
            ->andWhere('v.organization = :org')
            ->setParameter('user', $user)
            ->setParameter('org', $org)
            ->getQuery()
            ->getArrayResult();

        $roles = [];
        foreach ($rows as $row) {
            $role = $row['role'];
            $roles[(int) $row['vehicleId']] = $role instanceof ShareRole ? $role : ShareRole::from($role);
        }

        return $roles;
    }
}
