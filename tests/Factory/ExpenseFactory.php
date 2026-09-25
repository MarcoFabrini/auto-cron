<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Expense;
use App\Enum\ExpenseCategory;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Expense>
 */
final class ExpenseFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Expense::class;
    }

    protected function defaults(): array
    {
        $vehicle = VehicleFactory::createOne();
        return [
            'organization' => $vehicle->getOrganization(),
            'vehicle' => $vehicle,
            'occurredAt' => new \DateTimeImmutable(self::faker()->dateTimeBetween('-1 year')->format('Y-m-d')),
            'category' => ExpenseCategory::OTHER,
            'description' => self::faker()->sentence(),
            'amount' => (string) self::faker()->randomFloat(2, 5, 500),
        ];
    }
}
