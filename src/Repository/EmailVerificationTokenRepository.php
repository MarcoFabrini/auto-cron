<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EmailVerificationToken>
 */
class EmailVerificationTokenRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailVerificationToken::class);
    }

    public function findValidByHash(string $tokenHash): ?EmailVerificationToken
    {
        $token = $this->findOneBy(['tokenHash' => $tokenHash]);
        return $token?->isValid() ? $token : null;
    }

    /** Invalida i token pendenti dell'utente: un solo token di verifica attivo per volta. */
    public function invalidateAllForUser(User $user): void
    {
        $this->createQueryBuilder('t')
            ->update()
            ->set('t.usedAt', ':now')
            ->where('t.user = :user')
            ->andWhere('t.usedAt IS NULL')
            ->setParameter('now', new \DateTimeImmutable())
            ->setParameter('user', $user)
            ->getQuery()
            ->execute();
    }

    public function deleteExpired(\DateTimeImmutable $olderThan): int
    {
        return (int) $this->createQueryBuilder('t')
            ->delete()
            ->where('t.expiresAt < :cutoff')
            ->setParameter('cutoff', $olderThan)
            ->getQuery()
            ->execute();
    }
}
