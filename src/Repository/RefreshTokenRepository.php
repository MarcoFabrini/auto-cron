<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\RefreshToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<RefreshToken>
 */
class RefreshTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, RefreshToken::class);
    }

    public function findValidByToken(string $token): ?RefreshToken
    {
        $rt = $this->findOneBy(['token' => $token]);
        return $rt?->isValid() ? $rt : null;
    }

    /** Revoca atomica: true solo per la richiesta che passa per prima (niente doppia rotazione concorrente). */
    public function revokeIfActive(RefreshToken $token): bool
    {
        $affected = $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->where('rt.id = :id')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('id', $token->getId())
            ->getQuery()
            ->execute();

        return $affected === 1;
    }

    public function countActiveForUser(User $user): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->select('COUNT(rt.id)')
            ->where('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('user', $user)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function revokeAllForUser(User $user): void
    {
        $this->createQueryBuilder('rt')
            ->update()
            ->set('rt.revokedAt', ':now')
            ->where('rt.user = :user')
            ->andWhere('rt.revokedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpiredAndRevoked(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->createQueryBuilder('rt')
            ->delete()
            ->where('rt.expiresAt < :cutoff OR rt.revokedAt < :cutoff')
            ->setParameter('cutoff', $olderThan)
            ->getQuery()
            ->execute();
    }
}
