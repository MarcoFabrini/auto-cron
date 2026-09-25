<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Maintenance;
use App\Entity\Organization;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Maintenance>
 */
class MaintenanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Maintenance::class);
    }

    public function findOneInOrganization(int $id, Organization $org): ?Maintenance
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /** @return list<Maintenance> */
    public function findByVehiclePaginated(Vehicle $vehicle, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        return $this->createQueryBuilder('m')
            ->where('m.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('m.performedAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
