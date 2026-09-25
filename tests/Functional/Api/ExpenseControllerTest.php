<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Tests\Factory\ExpenseFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Support\ApiTestCase;

final class ExpenseControllerTest extends ApiTestCase
{
    public function testCreateExpenseWithRecurringPeriod(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/expenses', [
            'vehicleId' => $vehicle->getId(),
            'occurredAt' => '2026-01-15',
            'category' => 'insurance',
            'description' => 'Polizza annuale',
            'amount' => '650.00',
            'recurring' => true,
            'recurringPeriod' => 'yearly',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertTrue($this->jsonBody()['recurring']);
        self::assertSame('yearly', $this->jsonBody()['recurringPeriod']);
    }

    public function testCreateNonRecurringExpense(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/expenses', [
            'vehicleId' => $vehicle->getId(),
            'occurredAt' => '2026-02-01',
            'category' => 'fine',
            'description' => 'Multa autovelox',
            'amount' => '85.00',
            'recurring' => false,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertFalse($this->jsonBody()['recurring']);
    }

    public function testValidateAmountFormat(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/expenses', [
            'vehicleId' => $vehicle->getId(),
            'occurredAt' => '2026-01-01',
            'category' => 'tax',
            'description' => 'X',
            'amount' => 'abc',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testListReturnsExpensesSeededViaFactory(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        ExpenseFactory::createMany(3, ['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('GET', '/api/expenses?vehicleId='.$vehicle->getId(), accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->jsonBody());
    }
}
