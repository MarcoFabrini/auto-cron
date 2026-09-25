<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\Organization;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<AuditLog>
 */
class AuditLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, AuditLog::class);
    }

    /**
     * @return list<AuditLog>
     */
    public function findByOrganization(Organization $org, int $limit = 100, int $offset = 0): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.organization = :org')
            ->setParameter('org', $org)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<AuditLog>
     */
    public function findByEntity(string $entityClass, string $entityId, int $limit = 50): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.entityClass = :cls')
            ->andWhere('a.entityId = :id')
            ->setParameter('cls', $entityClass)
            ->setParameter('id', $entityId)
            ->orderBy('a.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
