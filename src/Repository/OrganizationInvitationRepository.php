<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\Organization;
use App\Entity\OrganizationInvitation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<OrganizationInvitation>
 */
class OrganizationInvitationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, OrganizationInvitation::class);
    }

    public function findValidByHash(string $tokenHash): ?OrganizationInvitation
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash]);
        return $token?->isValid() ? $token : null;
    }

    /**
     * Inviti pendenti (non usati, non scaduti) di un'organizzazione.
     *
     * @return list<OrganizationInvitation>
     */
    public function findPendingByOrganization(Organization $org): array
    {
        return $this->createQueryBuilder('i')
            ->where('i.organization = :org')
            ->andWhere('i.usedAt IS NULL')
            ->andWhere('i.expiresAt > :now')
            ->setParameter('org', $org)
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('i.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /** Marca usati gli inviti pendenti per (org, email): un solo invito attivo per coppia. */
    public function invalidateForOrgEmail(Organization $org, string $email): void
    {
        $this->createQueryBuilder('i')
            ->update()
            ->set('i.usedAt', ':now')
            ->where('i.organization = :org')
            ->andWhere('i.email = :email')
            ->andWhere('i.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('org', $org)
            ->setParameter('email', $email)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->createQueryBuilder('i')
            ->delete()
            ->where('i.expiresAt < :cutoff')
            ->setParameter('cutoff', $olderThan)
            ->getQuery()
            ->execute();
    }
}
