<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\VehicleShare;
use App\Enum\ShareRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<VehicleShare>
 */
final class VehicleShareFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return VehicleShare::class;
    }

    protected function defaults(): array
    {
        return [
            'vehicle' => VehicleFactory::new(),
            'user' => UserFactory::new(),
            'role' => ShareRole::VIEWER,
            'acceptedAt' => new \DateTimeImmutable(),
        ];
    }

    public function asEditor(): self { return $this->with(['role' => ShareRole::EDITOR]); }
    public function asAdmin(): self { return $this->with(['role' => ShareRole::ADMIN]); }
}
