<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Vehicle;
use App\Enum\FuelType;
use App\Enum\VehicleType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Vehicle>
 */
final class VehicleFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Vehicle::class;
    }

    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'name' => self::faker()->randomElement(['La Golf', 'La Panda', 'Il SUV', 'La Y']),
            'brand' => self::faker()->randomElement(['Volkswagen', 'Fiat', 'BMW', 'Audi']),
            'model' => self::faker()->bothify('Modello ##'),
            'year' => self::faker()->numberBetween(2005, 2024),
            'type' => VehicleType::CAR,
            'fuelType' => FuelType::DIESEL,
            'initialKm' => self::faker()->numberBetween(0, 100000),
        ];
    }
}
