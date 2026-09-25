<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\FuelType;
use App\Tests\Factory\RefuelingFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Support\ApiTestCase;

final class RefuelingControllerTest extends ApiTestCase
{
    public function testListRequiresVehicleId(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        $this->jsonRequest('GET', '/api/refuelings', accessToken: $token);
        self::assertResponseStatusCodeSame(400);
    }

    public function testCreateRefuelingComputesTotalCost(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/refuelings', [
            'vehicleId' => $vehicle->getId(),
            'refueledAt' => '2026-05-01',
            'km' => 50000,
            'liters' => '40.000',
            'pricePerLiter' => '1.8000',
            'fuelType' => 'diesel',
            'fullTank' => true,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        // total_cost è una GENERATED column del DB: 40 × 1.8 = 72.00
        self::assertSame('72.00', $this->jsonBody()['totalCost']);
    }

    public function testListByVehiclePaginated(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        RefuelingFactory::createMany(15, ['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('GET', '/api/refuelings?vehicleId='.$vehicle->getId().'&limit=5', accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertCount(5, $this->jsonBody());
    }

    public function testUpdateRefuelingRecomputesTotalCost(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = RefuelingFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'liters' => '30.000', 'pricePerLiter' => '1.5000',
        ]);

        $this->jsonRequest('PUT', '/api/refuelings/'.$r->getId(), [
            'vehicleId' => $vehicle->getId(),
            'refueledAt' => '2026-05-01', 'km' => 60000,
            'liters' => '50.000', 'pricePerLiter' => '2.0000',
            'fuelType' => 'diesel', 'fullTank' => true,
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('100.00', $this->jsonBody()['totalCost']);
    }

    public function testDeleteRefueling(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = RefuelingFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);
        $id = $r->getId();

        $this->jsonRequest('DELETE', '/api/refuelings/'.$id, accessToken: $token);
        self::assertResponseStatusCodeSame(204);
    }

    public function testCannotCreateRefuelingOnCrossTenantVehicle(): void
    {
        [, , $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);

        $this->jsonRequest('POST', '/api/refuelings', [
            'vehicleId' => $vehicleB->getId(),
            'refueledAt' => '2026-05-01', 'km' => 100,
            'liters' => '20.000', 'pricePerLiter' => '1.5000',
            'fuelType' => 'gasoline', 'fullTank' => true,
        ], accessToken: $tokenA);

        self::assertResponseStatusCodeSame(404);
    }
}
