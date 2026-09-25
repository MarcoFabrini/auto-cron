<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Reminder;
use App\Enum\ReminderType;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Reminder>
 */
final class ReminderFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Reminder::class;
    }

    protected function defaults(): array
    {
        $vehicle = VehicleFactory::createOne();
        return [
            'organization' => $vehicle->getOrganization(),
            'vehicle' => $vehicle,
            'type' => ReminderType::INSPECTION,
            'description' => self::faker()->sentence(4),
            'dueDate' => new \DateTimeImmutable('+'.self::faker()->numberBetween(1, 365).' days'),
            'notifyDaysBefore' => 30,
        ];
    }
}
