<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\RefreshToken;
use App\Entity\User;
use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\EntityManagerInterface;

final class RefreshTokenService
{
    public const TTL_DAYS = 7;
    public const COOKIE_NAME = 'refresh_token';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly RefreshTokenRepository $repo,
    ) {
    }

    public function issue(User $user, ?int $activeOrganizationId = null): RefreshToken
    {
        $token = bin2hex(random_bytes(48)); // 96 chars, consegnato al client e mai salvato
        $expiresAt = (new \DateTimeImmutable())->modify('+'.self::TTL_DAYS.' days');

        // In DB solo l'hash: un dump o un backup non permettono di rubare le sessioni attive.
        $refreshToken = new RefreshToken($user, self::hash($token), $expiresAt);
        $refreshToken->setPlainToken($token);
        $refreshToken->setActiveOrganizationId($activeOrganizationId);
        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    /**
     * Ruota il token mantenendo l'org attiva. Null se un'altra richiesta l'ha già ruotato
     * (due refresh concorrenti con lo stesso token non ottengono due sessioni).
     */
    public function rotate(RefreshToken $oldToken): ?RefreshToken
    {
        if (!$this->repo->revokeIfActive($oldToken)) {
            return null;
        }

        return $this->issue($oldToken->getUser(), $oldToken->getActiveOrganizationId());
    }

    public function setActiveOrganization(RefreshToken $token, int $organizationId): void
    {
        $token->setActiveOrganizationId($organizationId);
        $this->em->flush();
    }

    public function revoke(RefreshToken $token): void
    {
        $token->revoke();
        $this->em->flush();
    }

    public function revokeAllForUser(User $user): void
    {
        $this->repo->revokeAllForUser($user);
    }

    public function findValid(string $token): ?RefreshToken
    {
        return $this->repo->findValidByToken(self::hash($token));
    }

    /** Il token ha 384 bit di entropia: sha256 senza sale basta (non è una password). */
    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }
}
