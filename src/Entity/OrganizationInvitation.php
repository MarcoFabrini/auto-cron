<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\OrgRole;
use App\Repository\OrganizationInvitationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Invito a un'organizzazione legato a un INDIRIZZO EMAIL (non a un utente: chi
 * viene invitato può non avere ancora un account). All'accettazione si crea la
 * membership accepted; se l'utente non esiste, lo si registra contestualmente.
 *
 * Salviamo solo l'hash SHA-256 del token; il valore in chiaro viaggia solo
 * nell'email. expiresAt 7 giorni, usedAt marca l'uso (monouso).
 */
#[ORM\Entity(repositoryClass: OrganizationInvitationRepository::class)]
#[ORM\Table(name: 'organization_invitations')]
#[ORM\Index(name: 'idx_org_invitation_org', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_org_invitation_email', columns: ['email'])]
#[ORM\Index(name: 'idx_org_invitation_expires', columns: ['expires_at'])]
class OrganizationInvitation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['invitation:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(length: 180)]
    #[Groups(['invitation:read'])]
    private string $email;

    #[ORM\Column(length: 20, enumType: OrgRole::class)]
    #[Groups(['invitation:read'])]
    private OrgRole $role;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by', nullable: true, onDelete: 'SET NULL')]
    private ?User $invitedBy = null;

    #[ORM\Column(name: 'token_hash', length: 64, unique: true)]
    private string $tokenHash;

    #[ORM\Column(name: 'expires_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $expiresAt;

    #[ORM\Column(name: 'used_at', type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $usedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['invitation:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        Organization $organization,
        string $email,
        OrgRole $role,
        string $tokenHash,
        \DateTimeImmutable $expiresAt,
        ?User $invitedBy = null,
    ) {
        $this->organization = $organization;
        $this->email = $email;
        $this->role = $role;
        $this->tokenHash = $tokenHash;
        $this->expiresAt = $expiresAt;
        $this->invitedBy = $invitedBy;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOrganization(): Organization
    {
        return $this->organization;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getRole(): OrgRole
    {
        return $this->role;
    }

    public function getInvitedBy(): ?User
    {
        return $this->invitedBy;
    }

    public function getTokenHash(): string
    {
        return $this->tokenHash;
    }

    public function getExpiresAt(): \DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function markUsed(): void
    {
        $this->usedAt = new \DateTimeImmutable();
    }

    public function isExpired(): bool
    {
        return $this->expiresAt < new \DateTimeImmutable();
    }

    public function isUsed(): bool
    {
        return $this->usedAt !== null;
    }

    public function isValid(): bool
    {
        return !$this->isExpired() && !$this->isUsed();
    }
}
