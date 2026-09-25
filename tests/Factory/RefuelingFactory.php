<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Refueling;
use App\Enum\FuelType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Refueling>
 */
final class RefuelingFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Refueling::class;
    }

    protected function defaults(): array
    {
        $vehicle = VehicleFactory::createOne();
        return [
            'organization' => $vehicle->getOrganization(),
            'vehicle' => $vehicle,
            'refueledAt' => new \DateTimeImmutable(self::faker()->dateTimeBetween('-6 months')->format('Y-m-d')),
            'km' => self::faker()->numberBetween(0, 200000),
            'liters' => (string) self::faker()->randomFloat(3, 10, 60),
            'pricePerLiter' => (string) self::faker()->randomFloat(4, 1.5, 2.2),
            'fuelType' => FuelType::DIESEL,
            'fullTank' => true,
        ];
    }
}
