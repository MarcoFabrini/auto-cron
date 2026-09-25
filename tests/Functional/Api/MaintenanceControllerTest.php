<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Factory\MaintenanceFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Support\ApiTestCase;

final class MaintenanceControllerTest extends ApiTestCase
{
    public function testListRequiresVehicleIdQueryParam(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        $this->jsonRequest('GET', '/api/maintenances', accessToken: $token);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('query.vehicle_id_required', $this->jsonBody()['title']);
    }

    public function testListReturnsMaintenancesForVehicleScopedToOrg(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        MaintenanceFactory::createMany(3, ['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('GET', '/api/maintenances?vehicleId='.$vehicle->getId(), accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->jsonBody());
    }

    public function testCreateMaintenancePersists(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/maintenances', [
            'vehicleId' => $vehicle->getId(),
            'performedAt' => '2026-05-01',
            'km' => 50000,
            'type' => 'oil_change',
            'category' => 'scheduled',
            'description' => 'Cambio olio 5W30',
            'cost' => '120.50',
            'workshop' => 'Officina Rossi',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonBody();
        self::assertSame('120.50', $body['cost']);
        self::assertSame($vehicle->getId(), $body['vehicle']['id']);
    }

    public function testCreateValidatesAmountFormat(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/maintenances', [
            'vehicleId' => $vehicle->getId(),
            'performedAt' => '2026-05-01',
            'km' => 50000,
            'type' => 'oil_change',
            'description' => 'test',
            'cost' => 'not-a-number',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateMaintenance(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $maintenance = MaintenanceFactory::createOne([
            'organization' => $org,
            'vehicle' => $vehicle,
            'description' => 'old',
        ]);

        $this->jsonRequest('PUT', '/api/maintenances/'.$maintenance->getId(), [
            'vehicleId' => $vehicle->getId(),
            'performedAt' => '2026-06-01',
            'km' => 51000,
            'type' => 'filters',
            'category' => 'scheduled',
            'description' => 'updated description',
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('updated description', $this->jsonBody()['description']);
    }

    public function testDeleteMaintenance(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $maintenance = MaintenanceFactory::createOne([
            'organization' => $org,
            'vehicle' => $vehicle,
        ]);

        $this->jsonRequest('DELETE', '/api/maintenances/'.$maintenance->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(204);
    }

    public function testListPaginationRespectsLimit(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        MaintenanceFactory::createMany(25, ['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('GET', '/api/maintenances?vehicleId='.$vehicle->getId().'&page=1&limit=10', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(10, $this->jsonBody());
    }
}
