<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\AuditLog;
use App\Entity\Organization;
use App\Entity\User;
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
            // `id` come spareggio: il timestamp ha la precisione del secondo e molte righe (un import, un
            // flush con più entità) lo condividono; senza un ordine totale la paginazione a offset può
            // ripetere o saltare righe tra una pagina e l'altra.
            ->orderBy('a.createdAt', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    /**
     * Righe di audit generate dall'utente, dalla più vecchia (export GDPR).
     *
     * @return list<AuditLog>
     */
    public function findByUser(User $user): array
    {
        return $this->createQueryBuilder('a')
            ->where('a.user = :user')
            ->setParameter('user', $user)
            ->orderBy('a.createdAt', 'ASC')
            ->addOrderBy('a.id', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
