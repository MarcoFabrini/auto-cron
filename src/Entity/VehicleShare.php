<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\ShareRole;
use App\Repository\VehicleShareRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

#[ORM\Entity(repositoryClass: VehicleShareRepository::class)]
#[ORM\Table(name: 'vehicle_shares')]
#[ORM\UniqueConstraint(name: 'uniq_vehicle_user', columns: ['vehicle_id', 'user_id'])]
#[ORM\Index(name: 'idx_vehicle_shares_vehicle', columns: ['vehicle_id'])]
#[ORM\Index(name: 'idx_vehicle_shares_user', columns: ['user_id'])]
class VehicleShare
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['share:read', 'vehicle:read'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Vehicle::class, inversedBy: 'shares')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Vehicle $vehicle;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['share:read', 'vehicle:read'])]
    private User $user;

    #[ORM\Column(length: 20, enumType: ShareRole::class, options: ['default' => 'viewer'])]
    #[Groups(['share:read', 'vehicle:read', 'share:write'])]
    private ShareRole $role = ShareRole::VIEWER;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'invited_by', nullable: true, onDelete: 'SET NULL')]
    #[Groups(['share:read'])]
    private ?User $invitedBy = null;

    #[ORM\Column(name: 'accepted_at', type: 'datetime_immutable', nullable: true)]
    #[Groups(['share:read'])]
    private ?\DateTimeImmutable $acceptedAt = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['share:read'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getVehicle(): Vehicle { return $this->vehicle; }
    public function setVehicle(Vehicle $v): self { $this->vehicle = $v; return $this; }
    public function getUser(): User { return $this->user; }
    public function setUser(User $u): self { $this->user = $u; return $this; }
    public function getRole(): ShareRole { return $this->role; }
    public function setRole(ShareRole $r): self { $this->role = $r; return $this; }
    public function getInvitedBy(): ?User { return $this->invitedBy; }
    public function setInvitedBy(?User $u): self { $this->invitedBy = $u; return $this; }
    public function getAcceptedAt(): ?\DateTimeImmutable { return $this->acceptedAt; }
    public function setAcceptedAt(?\DateTimeImmutable $d): self { $this->acceptedAt = $d; return $this; }
    public function isAccepted(): bool { return $this->acceptedAt !== null; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
