<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\Organization;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<Organization>
 */
final class OrganizationFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return Organization::class;
    }

    protected function defaults(): array
    {
        $name = self::faker()->company();
        return [
            'name' => $name,
            'slug' => strtolower(self::faker()->unique()->slug(2)),
        ];
    }
}
