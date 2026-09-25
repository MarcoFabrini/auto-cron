<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Enum\OrgRole;
use App\Enum\ShareRole;
use App\Service\VehicleAccessChecker;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Unit-ish test del servizio VehicleAccessChecker.
 *
 * Pur essendo una "logica pura", il servizio si appoggia ai Repository Doctrine,
 * quindi serve un KernelTestCase + DB (più "integration" che "unit" puro).
 * Tradeoff accettabile: le factory pongono lo stato in 1-2 righe e il test
 * verifica realmente la query SQL + la logica di branching.
 */
final class VehicleAccessCheckerTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private VehicleAccessChecker $checker;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->checker = static::getContainer()->get(VehicleAccessChecker::class);
    }

    public function testOrgOwnerCanViewEditDelete(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::OWNER,
        ]);

        self::assertSame('org_admin', $this->checker->level($user, $vehicle));
        self::assertTrue($this->checker->canView($user, $vehicle));
        self::assertTrue($this->checker->canEdit($user, $vehicle));
        self::assertTrue($this->checker->canDelete($user, $vehicle));
    }

    public function testOrgAdminHasSameAccessAsOwner(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::ADMIN,
        ]);

        self::assertSame('org_admin', $this->checker->level($user, $vehicle));
    }

    public function testPlainMemberWithoutShareHasNoAccess(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::MEMBER,
        ]);

        self::assertNull($this->checker->level($user, $vehicle));
        self::assertFalse($this->checker->canView($user, $vehicle));
    }

    public function testMemberWithViewerShareCanViewOnly(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::MEMBER,
        ]);
        VehicleShareFactory::createOne([
            'vehicle' => $vehicle,
            'user' => $user,
            'role' => ShareRole::VIEWER,
        ]);

        self::assertSame('share_viewer', $this->checker->level($user, $vehicle));
        self::assertTrue($this->checker->canView($user, $vehicle));
        self::assertFalse($this->checker->canEdit($user, $vehicle));
        self::assertFalse($this->checker->canDelete($user, $vehicle));
    }

    public function testMemberWithEditorShareCanEditButNotDelete(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::MEMBER,
        ]);
        VehicleShareFactory::createOne([
            'vehicle' => $vehicle,
            'user' => $user,
            'role' => ShareRole::EDITOR,
        ]);

        self::assertSame('share_editor', $this->checker->level($user, $vehicle));
        self::assertTrue($this->checker->canEdit($user, $vehicle));
        self::assertFalse($this->checker->canDelete($user, $vehicle));
    }

    public function testMemberWithAdminShareCanDelete(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::MEMBER,
        ]);
        VehicleShareFactory::createOne([
            'vehicle' => $vehicle,
            'user' => $user,
            'role' => ShareRole::ADMIN,
        ]);

        self::assertSame('share_admin', $this->checker->level($user, $vehicle));
        self::assertTrue($this->checker->canDelete($user, $vehicle));
    }

    public function testUserNotMemberHasNoAccess(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        // niente membership creata: l'user non appartiene affatto all'org del vehicle

        self::assertNull($this->checker->level($user, $vehicle));
        self::assertFalse($this->checker->canView($user, $vehicle));
    }

    public function testUnacceptedMembershipDoesNotGrantAccess(): void
    {
        $user = UserFactory::createOne();
        $vehicle = VehicleFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $vehicle->getOrganization(),
            'role' => OrgRole::OWNER,
            'acceptedAt' => null, // pending invite
        ]);

        self::assertNull($this->checker->level($user, $vehicle));
    }
}
