<?php

declare(strict_types=1);

namespace App\Tests\Support;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ExpenseCategory;
use App\Enum\FuelType;
use App\Service\AppClock;
use App\Tests\Factory\ExpenseFactory;
use App\Tests\Factory\MaintenanceFactory;
use App\Tests\Factory\RefuelingFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Helper comuni ai test dei grafici (dashboard e singolo veicolo): date relative al mese corrente
 * di AppClock, veicoli di proprietà, token per un'org e record di rifornimenti/manutenzioni/spese.
 */
trait ChartFixtures
{
    /** Primo giorno (o `$day`-esimo) del mese a `$offset` mesi dal corrente, nel fuso dell'istanza. */
    private function day(int $offset, int $day = 1): \DateTimeImmutable
    {
        return static::getContainer()->get(AppClock::class)->today()
            ->modify('first day of this month')
            ->modify(sprintf('%+d months', $offset))
            ->modify(sprintf('+%d days', $day - 1));
    }

    private function monthKey(int $offset): string
    {
        return $this->day($offset)->format('Y-m');
    }

    /** @param array<string, mixed> $attrs */
    private function ownedVehicle(User $owner, Organization $org, array $attrs = []): Vehicle
    {
        $vehicle = VehicleFactory::createOne($attrs + ['organization' => $org, 'fuelType' => FuelType::DIESEL]);
        VehicleShareFactory::new()->asAdmin()->create(['vehicle' => $vehicle, 'user' => $owner]);

        return $vehicle;
    }

    private function tokenFor(User $user, Organization $org): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->createFromPayload($user, [
            'user_id' => $user->getId(),
            'active_org_id' => $org->getId(),
        ]);
    }

    private function refueling(Vehicle $vehicle, \DateTimeImmutable $on, int $km, string $liters, string $pricePerLiter, bool $full = true, FuelType $fuel = FuelType::DIESEL): void
    {
        RefuelingFactory::createOne([
            'organization' => $vehicle->getOrganization(), 'vehicle' => $vehicle,
            'refueledAt' => $on, 'km' => $km, 'liters' => $liters, 'pricePerLiter' => $pricePerLiter,
            'fuelType' => $fuel, 'fullTank' => $full,
        ]);
    }

    private function maintenance(Vehicle $vehicle, \DateTimeImmutable $on, int $km, ?string $cost): void
    {
        MaintenanceFactory::createOne([
            'organization' => $vehicle->getOrganization(), 'vehicle' => $vehicle,
            'performedAt' => $on, 'km' => $km, 'cost' => $cost,
        ]);
    }

    private function expense(Vehicle $vehicle, \DateTimeImmutable $on, string $amount, ExpenseCategory $category = ExpenseCategory::OTHER): void
    {
        ExpenseFactory::createOne([
            'organization' => $vehicle->getOrganization(), 'vehicle' => $vehicle,
            'occurredAt' => $on, 'amount' => $amount, 'category' => $category,
        ]);
    }
}
