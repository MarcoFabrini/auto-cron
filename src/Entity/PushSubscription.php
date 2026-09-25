<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\PushPlatform;
use App\Repository\PushSubscriptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Sottoscrizioni Web Push (browser): endpoint + p256dh + auth_secret (Web Push API).
 * Il CHECK constraint a livello DB garantisce che i campi web siano valorizzati.
 */
#[ORM\Entity(repositoryClass: PushSubscriptionRepository::class)]
#[ORM\Table(name: 'push_subscriptions')]
#[ORM\Index(name: 'idx_push_user', columns: ['user_id'])]
#[ORM\Index(name: 'idx_push_platform', columns: ['platform'])]
class PushSubscription
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['push:read', 'push:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 10, enumType: PushPlatform::class)]
    #[Groups(['push:read', 'push:list', 'push:write'])]
    private PushPlatform $platform;

    // ----- Web Push -----
    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['push:write'])]
    private ?string $endpoint = null;

    #[ORM\Column(type: 'text', nullable: true)]
    #[Groups(['push:write'])]
    private ?string $p256dh = null;

    #[ORM\Column(name: 'auth_secret', type: 'text', nullable: true)]
    #[Groups(['push:write'])]
    private ?string $authSecret = null;

    // ----- Common metadata -----
    #[ORM\Column(name: 'device_label', length: 120, nullable: true)]
    #[Groups(['push:read', 'push:list', 'push:write'])]
    private ?string $deviceLabel = null;

    #[ORM\Column(name: 'user_agent', length: 500, nullable: true)]
    #[Groups(['push:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['push:read', 'push:list'])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(name: 'last_seen_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['push:read'])]
    private ?\DateTimeImmutable $lastSeenAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getPlatform(): PushPlatform { return $this->platform; }
    public function setPlatform(PushPlatform $p): self { $this->platform = $p; return $this; }
    public function getEndpoint(): ?string { return $this->endpoint; }
    public function setEndpoint(?string $e): self { $this->endpoint = $e; return $this; }
    public function getP256dh(): ?string { return $this->p256dh; }
    public function setP256dh(?string $p): self { $this->p256dh = $p; return $this; }
    public function getAuthSecret(): ?string { return $this->authSecret; }
    public function setAuthSecret(?string $a): self { $this->authSecret = $a; return $this; }
    public function getDeviceLabel(): ?string { return $this->deviceLabel; }
    public function setDeviceLabel(?string $l): self { $this->deviceLabel = $l; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $u): self { $this->userAgent = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
    public function getLastSeenAt(): ?\DateTimeImmutable { return $this->lastSeenAt; }
    public function touchLastSeen(): self { $this->lastSeenAt = new \DateTimeImmutable(); return $this; }
}
