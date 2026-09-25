<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Maintenance;
use App\Enum\MaintenanceCategory;
use App\Enum\MaintenanceType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Maintenance>
 */
final class MaintenanceFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Maintenance::class;
    }

    protected function defaults(): array
    {
        $vehicle = VehicleFactory::createOne();
        return [
            'organization' => $vehicle->getOrganization(),
            'vehicle' => $vehicle,
            'performedAt' => new \DateTimeImmutable(self::faker()->dateTimeBetween('-1 year')->format('Y-m-d')),
            'km' => self::faker()->numberBetween(0, 200000),
            'type' => MaintenanceType::OIL_CHANGE,
            'category' => MaintenanceCategory::SCHEDULED,
            'description' => self::faker()->sentence(),
            'cost' => (string) self::faker()->randomFloat(2, 20, 500),
        ];
    }
}
