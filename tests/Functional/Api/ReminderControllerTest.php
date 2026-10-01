<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use App\Tests\Support\ApiTestCase;

final class ReminderControllerTest extends ApiTestCase
{
    public function testCreateReminder(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $vehicle->getId(),
            'type' => 'inspection',
            'description' => 'Revisione biennale',
            'dueDate' => '2026-12-31',
            'notifyDaysBefore' => 30,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Revisione biennale', $this->jsonBody()['description']);
        self::assertNull($this->jsonBody()['completedAt']);
    }

    public function testCreateReminderWithoutAnyDueIsRejected(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $vehicle->getId(),
            'type' => 'custom',
            'description' => 'Senza scadenza',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCompleteEndpointMarksAsCompleted(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('POST', '/api/reminders/'.$r->getId().'/complete', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->jsonBody()['completedAt']);
    }

    public function testListExcludesCompletedByDefault(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        ReminderFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'completedAt' => new \DateTimeImmutable(),
        ]);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$vehicle->getId(), accessToken: $token);
        self::assertCount(1, $this->jsonBody());

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$vehicle->getId().'&onlyActive=false', accessToken: $token);
        self::assertCount(2, $this->jsonBody());
    }

    public function testUpcomingAggregatesAcrossTheOwnedVehicles(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicleA = $this->ownedVehicle($user, $org);
        $vehicleB = $this->ownedVehicle($user, $org);

        $overdue = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleA,
            'dueDate' => new \DateTimeImmutable('-2 days'),
        ]);
        $soon = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleB,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleA,
            'dueDate' => new \DateTimeImmutable('+90 days'), // fuori dalla finestra default (30gg)
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $token);

        self::assertResponseIsSuccessful();
        /** @var list<array{id: int, vehicle: array{id: int}}> $body */
        $body = $this->jsonBody();
        self::assertSame([$overdue->getId(), $soon->getId()], array_column($body, 'id'));
        self::assertSame($vehicleA->getId(), $body[0]['vehicle']['id']);
    }

    public function testUpcomingIsolatesOtherOrganizations(): void
    {
        [, $orgA, $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);
        ReminderFactory::createOne([
            'organization' => $orgB, 'vehicle' => $vehicleB,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $tokenA);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->jsonBody());
    }

    public function testUpcomingOnlyCoversOwnedVehiclesEvenForTheOrgOwner(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser(); // owner dell'org: vede tutti i veicoli
        $mine = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $this->ownedVehicle($user, $org),
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        // Veicolo di un altro membro: l'owner dell'org lo vede, ma non è suo.
        $othersVehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::new()->asAdmin()->create(['vehicle' => $othersVehicle]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $othersVehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        // Veicolo condiviso con lui in sola lettura: nemmeno questo.
        $sharedVehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::createOne(['vehicle' => $sharedVehicle, 'user' => $user]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $sharedVehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame([$mine->getId()], array_column($this->jsonBody(), 'id'));
    }

    public function testUpcomingRespectsDaysAndLimitParams(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = $this->ownedVehicle($user, $org);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+50 days'),
        ]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming?days=90&limit=1', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonBody());
    }

    private function ownedVehicle(User $owner, Organization $org): Vehicle
    {
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::new()->asAdmin()->create(['vehicle' => $vehicle, 'user' => $owner]);

        return $vehicle;
    }
}
