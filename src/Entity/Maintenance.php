<?php

declare(strict_types=1);

namespace App\Entity;

use App\Entity\Concern\TimestampableTrait;
use App\Entity\Concern\VehicleScoped;
use App\Enum\MaintenanceCategory;
use App\Enum\MaintenanceType;
use App\Repository\MaintenanceRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: MaintenanceRepository::class)]
#[ORM\Table(name: 'maintenances')]
#[ORM\Index(name: 'idx_maintenances_org', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_maintenances_vehicle_date', columns: ['vehicle_id', 'performed_at'])]
#[ORM\HasLifecycleCallbacks]
class Maintenance implements VehicleScoped
{
    use TimestampableTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['maintenance:read', 'maintenance:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\ManyToOne(targetEntity: Vehicle::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    #[Groups(['maintenance:read', 'maintenance:list'])]
    private Vehicle $vehicle;

    #[ORM\Column(name: 'performed_at', type: 'date_immutable')]
    #[Assert\NotNull]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private \DateTimeImmutable $performedAt;

    #[ORM\Column(type: 'integer')]
    #[Assert\PositiveOrZero]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private int $km;

    #[ORM\Column(length: 30, enumType: MaintenanceType::class)]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private MaintenanceType $type = MaintenanceType::OTHER;

    #[ORM\Column(type: 'text')]
    #[Assert\NotBlank]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private string $description;

    /** Money as string (decimal) — Doctrine maps NUMERIC(10,2) → PHP string for precision. */
    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private ?string $cost = null;

    #[ORM\Column(length: 200, nullable: true)]
    #[Assert\Length(max: 200)]
    #[Groups(['maintenance:read', 'maintenance:write'])]
    private ?string $workshop = null;

    #[ORM\Column(length: 20, enumType: MaintenanceCategory::class, options: ['default' => 'scheduled'])]
    #[Groups(['maintenance:read', 'maintenance:list', 'maintenance:write'])]
    private MaintenanceCategory $category = MaintenanceCategory::SCHEDULED;

    public function getId(): ?int { return $this->id; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $o): self { $this->organization = $o; return $this; }
    public function getVehicle(): Vehicle { return $this->vehicle; }
    public function setVehicle(Vehicle $v): self { $this->vehicle = $v; return $this; }
    public function getPerformedAt(): \DateTimeImmutable { return $this->performedAt; }
    public function setPerformedAt(\DateTimeImmutable $d): self { $this->performedAt = $d; return $this; }
    public function getKm(): int { return $this->km; }
    public function setKm(int $k): self { $this->km = $k; return $this; }
    public function getType(): MaintenanceType { return $this->type; }
    public function setType(MaintenanceType $t): self { $this->type = $t; return $this; }
    public function getDescription(): string { return $this->description; }
    public function setDescription(string $d): self { $this->description = $d; return $this; }
    public function getCost(): ?string { return $this->cost; }
    public function setCost(?string $c): self { $this->cost = $c; return $this; }
    public function getWorkshop(): ?string { return $this->workshop; }
    public function setWorkshop(?string $w): self { $this->workshop = $w; return $this; }
    public function getCategory(): MaintenanceCategory { return $this->category; }
    public function setCategory(MaintenanceCategory $c): self { $this->category = $c; return $this; }
}
