<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\AttachmentEntityType;
use App\Repository\AttachmentRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Attribute\Groups;

/**
 * Allegati polimorfici: la coppia (entityType, entityId) referenzia logicamente
 * un'altra tabella (vehicle/maintenance/expense). Non c'è FK reale — la coerenza
 * è garantita dal codice applicativo (Voter + service) e dal cascade applicativo.
 */
#[ORM\Entity(repositoryClass: AttachmentRepository::class)]
#[ORM\Table(name: 'attachments')]
#[ORM\Index(name: 'idx_attachments_org', columns: ['organization_id'])]
#[ORM\Index(name: 'idx_attachments_entity', columns: ['entity_type', 'entity_id'])]
class Attachment
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    #[Groups(['attachment:read', 'attachment:list'])]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Organization::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Organization $organization;

    #[ORM\Column(name: 'entity_type', length: 30, enumType: AttachmentEntityType::class)]
    #[Groups(['attachment:read', 'attachment:list'])]
    private AttachmentEntityType $entityType;

    #[ORM\Column(name: 'entity_id', type: 'bigint')]
    #[Groups(['attachment:read', 'attachment:list'])]
    private string $entityId;

    #[ORM\Column(name: 'original_filename', length: 255)]
    #[Groups(['attachment:read', 'attachment:list'])]
    private string $originalFilename;

    #[ORM\Column(name: 'stored_path', length: 500)]
    private string $storedPath;

    #[ORM\Column(name: 'mime_type', length: 100)]
    #[Groups(['attachment:read', 'attachment:list'])]
    private string $mimeType;

    #[ORM\Column(name: 'size_bytes', type: 'integer')]
    #[Groups(['attachment:read', 'attachment:list'])]
    private int $sizeBytes;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'uploaded_by', nullable: false, onDelete: 'CASCADE')]
    #[Groups(['attachment:read'])]
    private User $uploadedBy;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable', options: ['default' => 'CURRENT_TIMESTAMP'])]
    #[Groups(['attachment:read', 'attachment:list'])]
    private \DateTimeImmutable $createdAt;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
    }

    public function getId(): ?int { return $this->id; }
    public function getOrganization(): Organization { return $this->organization; }
    public function setOrganization(Organization $o): self { $this->organization = $o; return $this; }
    public function getEntityType(): AttachmentEntityType { return $this->entityType; }
    public function setEntityType(AttachmentEntityType $t): self { $this->entityType = $t; return $this; }
    public function getEntityId(): string { return $this->entityId; }
    public function setEntityId(string|int $id): self { $this->entityId = (string) $id; return $this; }
    public function getOriginalFilename(): string { return $this->originalFilename; }
    public function setOriginalFilename(string $n): self { $this->originalFilename = $n; return $this; }
    public function getStoredPath(): string { return $this->storedPath; }
    public function setStoredPath(string $p): self { $this->storedPath = $p; return $this; }
    public function getMimeType(): string { return $this->mimeType; }
    public function setMimeType(string $m): self { $this->mimeType = $m; return $this; }
    public function getSizeBytes(): int { return $this->sizeBytes; }
    public function setSizeBytes(int $s): self { $this->sizeBytes = $s; return $this; }
    public function getUploadedBy(): User { return $this->uploadedBy; }
    public function setUploadedBy(User $u): self { $this->uploadedBy = $u; return $this; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
