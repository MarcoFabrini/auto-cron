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
}
