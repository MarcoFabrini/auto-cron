<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AuditAction;
use App\Repository\AuditLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Audit log immutabile delle scritture sui dati di un'org (#3.6).
 *
 * Append-only: nessuna UPDATE/DELETE applicativa, solo INSERT. Pulizia
 * eventualmente via console (retention policy).
 */
#[ORM\Entity(repositoryClass: AuditLogRepository::class)]
#[ORM\Table(name: 'audit_logs')]
#[ORM\Index(name: 'idx_audit_org_created', columns: ['organization_id', 'created_at'])]
#[ORM\Index(name: 'idx_audit_entity', columns: ['entity_class', 'entity_id'])]
#[ORM\Index(name: 'idx_audit_user', columns: ['user_id'])]
class AuditLog
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['audit:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Organization $organization = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?User $user = null;

    #[ORM\Column(name: 'entity_class', length: 120)]
    #[Groups(['audit:read'])]
    private string $entityClass;

    #[ORM\Column(name: 'entity_id', length: 60)]
    #[Groups(['audit:read'])]
    private string $entityId;

    #[ORM\Column(length: 20, enumType: AuditAction::class)]
    #[Groups(['audit:read'])]
    private AuditAction $action;

    /**
     * @var array<string, array{0: mixed, 1: mixed}>|null Diff field => [old, new]; null per CREATED/DELETED.
     */
    #[ORM\Column(type: 'json', nullable: true)]
    #[Groups(['audit:read'])]
    private ?array $changes = null;

    #[ORM\Column(name: 'ip_address', length: 45, nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $ipAddress = null;

    #[ORM\Column(name: 'user_agent', type: 'text', nullable: true)]
    #[Groups(['audit:read'])]
    private ?string $userAgent = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['audit:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganization(): ?Organization { return $this->organization; }
    public function setOrganization(?Organization $o): self { $this->organization = $o; return $this; }
    public function getUser(): ?User { return $this->user; }
    public function setUser(?User $u): self { $this->user = $u; return $this; }
    public function getEntityClass(): string { return $this->entityClass; }
    public function setEntityClass(string $c): self { $this->entityClass = $c; return $this; }
    public function getEntityId(): string { return $this->entityId; }
    public function setEntityId(string $i): self { $this->entityId = $i; return $this; }
    public function getAction(): AuditAction { return $this->action; }
    public function setAction(AuditAction $a): self { $this->action = $a; return $this; }
    /** @return array<string, array{0: mixed, 1: mixed}>|null */
    public function getChanges(): ?array { return $this->changes; }
    /** @param array<string, array{0: mixed, 1: mixed}>|null $c */
    public function setChanges(?array $c): self { $this->changes = $c; return $this; }
    public function getIpAddress(): ?string { return $this->ipAddress; }
    public function setIpAddress(?string $ip): self { $this->ipAddress = $ip; return $this; }
    public function getUserAgent(): ?string { return $this->userAgent; }
    public function setUserAgent(?string $ua): self { $this->userAgent = $ua; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
