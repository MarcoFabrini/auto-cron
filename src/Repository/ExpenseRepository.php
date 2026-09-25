<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Expense;
use App\Entity\Organization;
use App\Entity\Vehicle;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Expense>
 */
class ExpenseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Expense::class);
    }

    public function findOneInOrganization(int $id, Organization $org): ?Expense
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /** @return list<Expense> */
    public function findByVehiclePaginated(Vehicle $vehicle, int $page = 1, int $limit = 20): array
    {
        $offset = max(0, ($page - 1) * $limit);
        return $this->createQueryBuilder('e')
            ->where('e.vehicle = :vehicle')
            ->setParameter('vehicle', $vehicle)
            ->orderBy('e.occurredAt', 'DESC')
            ->addOrderBy('e.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
