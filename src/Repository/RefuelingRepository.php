<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\Refueling;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Refueling>
 */
class RefuelingRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Refueling::class);
    }

    public function findOneInOrganization(int $id, Organization $org): ?Refueling
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /** @return list<Refueling> */
    public function findByVehiclePaginated(Vehicle $vehicle, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        return $this->createQueryBuilder('r')
            ->where('r.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('r.refueledAt', 'DESC')
            ->addOrderBy('r.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
