<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\PushSettingsRepository;
use Doctrine\ORM\Mapping as ORM;

/**
 * Configurazione Web Push (VAPID) a livello di ISTANZA (self-host): una sola riga.
 * La public key NON è segreta (il browser la riceve per sottoscriversi); la private
 * key è cifrata at-rest (SecretCipher, chiave da APP_SECRET) e non esce mai dall'API.
 * Quando `enabled` è false (o la config è incompleta) le notifiche push sono
 * disattivate — nessun fallback via env, si configura solo da UI.
 *
 * NB: rigenerare la coppia VAPID invalida tutte le subscription esistenti
 * (la public key è incastonata in ognuna).
 */
#[ORM\Entity(repositoryClass: PushSettingsRepository::class)]
#[ORM\Table(name: 'push_settings')]
class PushSettings
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'bigint')]
    private ?int $id = null;

    #[ORM\Column(name: 'vapid_public_key', type: 'text')]
    private string $publicKey = '';

    #[ORM\Column(name: 'vapid_private_key_cipher', type: 'text', nullable: true)]
    private ?string $privateKeyCipher = null;

    #[ORM\Column(name: 'vapid_subject', length: 255)]
    private string $subject = '';

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $enabled = false;

    #[ORM\Column(name: 'updated_at', type: 'datetime_immutable')]
    private \DateTimeImmutable $updatedAt;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'updated_by_id', nullable: true, onDelete: 'SET NULL')]
    private ?User $updatedBy = null;

    public function __construct()
    {
        $this->updatedAt = new \DateTimeImmutable();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPublicKey(): string
    {
        return $this->publicKey;
    }

    public function setPublicKey(string $publicKey): self
    {
        $this->publicKey = $publicKey;
        return $this;
    }

    public function getPrivateKeyCipher(): ?string
    {
        return $this->privateKeyCipher;
    }

    public function setPrivateKeyCipher(?string $privateKeyCipher): self
    {
        $this->privateKeyCipher = $privateKeyCipher;
        return $this;
    }

    public function hasPrivateKey(): bool
    {
        return $this->privateKeyCipher !== null;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = trim($subject);
        return $this;
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function setEnabled(bool $enabled): self
    {
        $this->enabled = $enabled;
        return $this;
    }

    public function getUpdatedAt(): \DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function touch(?User $by): self
    {
        $this->updatedAt = new \DateTimeImmutable();
        $this->updatedBy = $by;
        return $this;
    }

    public function getUpdatedBy(): ?User
    {
        return $this->updatedBy;
    }

    /** Coppia VAPID presente: pubblica + privata cifrata. */
    public function isComplete(): bool
    {
        return $this->publicKey !== '' && $this->privateKeyCipher !== null;
    }
}
