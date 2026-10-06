<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Attachment;
use App\Entity\Organization;
use App\Enum\AttachmentEntityType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Attachment>
 */
class AttachmentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Attachment::class);
    }

    public function findOneInOrganization(int $id, Organization $org): ?Attachment
    {
        return $this->findOneBy(['id' => $id, 'organization' => $org]);
    }

    /** @return list<Attachment> */
    public function findByEntity(AttachmentEntityType $type, int|string $entityId, Organization $org): array
    {
        return $this->findBy(
            [
                'organization' => $org,
                'entityType' => $type,
                'entityId' => (string) $entityId,
            ],
            ['createdAt' => 'DESC'],
        );
    }

    /** Quanti allegati ha già un singolo record (stessa chiave di {@see findByEntity()}). */
    public function countByEntity(AttachmentEntityType $type, int|string $entityId, Organization $org): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COUNT(a.id)')
            ->where('a.organization = :org')
            ->andWhere('a.entityType = :type')
            ->andWhere('a.entityId = :entityId')
            ->setParameter('org', $org)
            ->setParameter('type', $type)
            ->setParameter('entityId', (string) $entityId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /** Byte occupati da tutti gli allegati dell'organizzazione (0 se non ne ha). */
    public function sumSizeBytesByOrganization(Organization $org): int
    {
        return (int) $this->createQueryBuilder('a')
            ->select('COALESCE(SUM(a.sizeBytes), 0)')
            ->where('a.organization = :org')
            ->setParameter('org', $org)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Allegati di molte entità dello stesso tipo in UNA query (export GDPR: niente query per record).
     * Il filtro per org è difesa in profondità: gli id arrivano già da record di quelle org.
     *
     * @param list<int|string>   $entityIds
     * @param list<Organization> $orgs
     *
     * @return array<int, list<Attachment>> entityId => allegati (più recenti prima)
     */
    public function findGroupedByEntityIds(AttachmentEntityType $type, array $entityIds, array $orgs): array
    {
        $grouped = [];
        if ($entityIds === [] || $orgs === []) {
            return $grouped;
        }

        // Blocchi da 1000 id: evita IN() enormi su utenti con molta storia.
        foreach (array_chunk(array_map('strval', $entityIds), 1000) as $chunk) {
            /** @var list<Attachment> $rows */
            $rows = $this->createQueryBuilder('a')
                ->where('a.entityType = :type')
                ->andWhere('a.entityId IN (:ids)')
                ->andWhere('a.organization IN (:orgs)')
                ->setParameter('type', $type)
                ->setParameter('ids', $chunk)
                ->setParameter('orgs', $orgs)
                ->orderBy('a.createdAt', 'DESC')
                ->addOrderBy('a.id', 'DESC')
                ->getQuery()
                ->getResult();
            foreach ($rows as $attachment) {
                $grouped[(int) $attachment->getEntityId()][] = $attachment;
            }
        }

        return $grouped;
    }
}
