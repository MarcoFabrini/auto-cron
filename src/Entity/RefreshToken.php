<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\RefreshTokenRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: RefreshTokenRepository::class)]
#[ORM\Table(name: 'refresh_tokens')]
#[ORM\Index(name: 'idx_refresh_tokens_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_refresh_tokens_expires', columns: ['expires_at'])]
class RefreshToken
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 128, unique: true)]
    private string $token;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'revoked_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $revokedAt = null;

    /**
     * Valore in chiaro del token, presente SOLO sull'istanza appena emessa da
     * {@see \App\Service\RefreshTokenService::issue()}: serve a consegnarlo al client (cookie/body)
     * e non viene mai persistito. In DB c'è solo `token` = sha256 del valore in chiaro.
     */
    private ?string $plainToken = null;

    /** Org attiva della sessione: sopravvive alla rotazione, così il refresh non riporta alla prima org. */
    #[ORM\Column(name: 'active_organization_id', type: 'integer', nullable: true)]
    private ?int $activeOrganizationId = null;

    public function __construct(User $user, string $token, \DateTimeImmutable $expiresAt)
    {
        $this->user = $user;
        $this->token = $token;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    /** Hash (sha256) del token, come salvato in DB. */
    public function getToken(): string
    {
        return $this->token;
    }

    public function getPlainToken(): string
    {
        return $this->plainToken ?? throw new \LogicException('Plain refresh token is only available right after issue().');
    }

    public function setPlainToken(string $plainToken): void
    {
        $this->plainToken = $plainToken;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function getRevokedAt(): ?\DateTimeImmutable
    {
        return $this->revokedAt;
    }

    public function getActiveOrganizationId(): ?int
    {
        return $this->activeOrganizationId;
    }

    public function setActiveOrganizationId(?int $id): void
    {
        $this->activeOrganizationId = $id;
    }

    public function revoke(): void
    {
        $this->revokedAt = new \DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isRevoked(): bool
    {
        return $this->revokedAt !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isRevoked();
    }
}
