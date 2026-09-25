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

    public function issue(User $user): RefreshToken
    {
        $token = bin2hex(random_bytes(48)); // 96 chars
        $expiresAt = (new \DateTimeImmutable())->modify('+'.self::TTL_DAYS.' days');

        $refreshToken = new RefreshToken($user, $token, $expiresAt);
        $this->em->persist($refreshToken);
        $this->em->flush();

        return $refreshToken;
    }

    public function rotate(RefreshToken $oldToken): RefreshToken
    {
        $oldToken->revoke();
        $newToken = $this->issue($oldToken->getUser());
        $this->em->flush();
        return $newToken;
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
        return $this->repo->findValidByToken($token);
    }
}
