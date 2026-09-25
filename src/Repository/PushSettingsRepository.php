<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\PushSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<PushSettings>
 */
class PushSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PushSettings::class);
    }

    /** Config di istanza: singola riga (o null se mai salvata). */
    public function get(): ?PushSettings
    {
        return $this->findOneBy([], ['id' => 'ASC']);
    }
}
