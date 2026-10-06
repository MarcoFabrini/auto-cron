<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Enum\FuelType;
use App\Repository\RefuelingRepository;
use App\Tests\Factory\RefuelingFactory;
use App\Tests\Factory\VehicleFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class RefuelingRepositoryTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testFindUsedFuelTypesIsDistinctAndScopedToTheVehicle(): void
    {
        self::bootKernel();
        $vehicle = VehicleFactory::createOne();
        $other = VehicleFactory::createOne();
        foreach ([FuelType::GASOLINE, FuelType::GASOLINE, FuelType::LPG] as $fuel) {
            RefuelingFactory::createOne(['organization' => $vehicle->getOrganization(), 'vehicle' => $vehicle, 'fuelType' => $fuel]);
        }
        RefuelingFactory::createOne(['organization' => $other->getOrganization(), 'vehicle' => $other, 'fuelType' => FuelType::DIESEL]);

        $used = static::getContainer()->get(RefuelingRepository::class)->findUsedFuelTypes($vehicle);

        $values = array_map(static fn (FuelType $fuel): string => $fuel->value, $used);
        sort($values);
        self::assertSame(['gasoline', 'lpg'], $values);
        self::assertSame([], static::getContainer()->get(RefuelingRepository::class)->findUsedFuelTypes(VehicleFactory::createOne()));
    }
}
