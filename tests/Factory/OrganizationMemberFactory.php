<?php

declare(strict_types=1);

namespace App\Tests\Factory;

use App\Entity\OrganizationMember;
use App\Enum\OrgRole;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<OrganizationMember>
 */
final class OrganizationMemberFactory extends PersistentObjectFactory
{
    public static function class(): string
    {
        return OrganizationMember::class;
    }

    protected function defaults(): array
    {
        return [
            'organization' => OrganizationFactory::new(),
            'user' => UserFactory::new(),
            'role' => OrgRole::MEMBER,
            'acceptedAt' => new \DateTimeImmutable(),
        ];
    }

    public function asOwner(): self
    {
        return $this->with(['role' => OrgRole::OWNER]);
    }

    public function asAdmin(): self
    {
        return $this->with(['role' => OrgRole::ADMIN]);
    }
}
