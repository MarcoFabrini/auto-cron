<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Service\Gdpr\GdprExportService;
use App\Tests\Factory\MaintenanceFactory;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Enum\OrgRole;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test GDPR Art. 20 export service (#5.4).
 *
 * Verifica:
 * - struttura output (user, memberships, organizations_owned, ecc.)
 * - password hash NON è incluso
 * - org dove user è MEMBER non finiscono in organizations_owned
 * - vehicles + nested data popolati
 */
final class GdprExportServiceTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testExportContainsUserProfile(): void
    {
        $user = UserFactory::createOne(['email' => 'export@test.it', 'firstName' => 'Mario', 'lastName' => 'Rossi']);
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        self::assertSame('export@test.it', $data['user']['email']);
        self::assertSame('Mario', $data['user']['first_name']);
        self::assertSame('Rossi', $data['user']['last_name']);
        self::assertArrayNotHasKey('password', $data['user'], 'Password hash must never be in GDPR export');
    }

    public function testExportListsMemberships(): void
    {
        $user = UserFactory::createOne();
        $orgA = OrganizationFactory::createOne(['slug' => 'org-a']);
        $orgB = OrganizationFactory::createOne(['slug' => 'org-b']);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $orgA, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $orgB, 'role' => OrgRole::MEMBER]);

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        self::assertCount(2, $data['memberships']);
        $slugs = array_column($data['memberships'], 'organization_slug');
        self::assertContains('org-a', $slugs);
        self::assertContains('org-b', $slugs);
    }

    public function testOrganizationsOwnedExcludesMemberOnlyOrgs(): void
    {
        $user = UserFactory::createOne();
        $orgOwned = OrganizationFactory::createOne(['slug' => 'owned']);
        $orgMember = OrganizationFactory::createOne(['slug' => 'member-only']);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $orgOwned, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $orgMember, 'role' => OrgRole::MEMBER]);

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        self::assertCount(1, $data['organizations_owned']);
        self::assertSame('owned', $data['organizations_owned'][0]['slug']);
    }

    public function testExportIncludesVehiclesAndMaintenance(): void
    {
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);
        $vehicle = VehicleFactory::createOne(['organization' => $org, 'name' => 'La Panda']);
        MaintenanceFactory::createOne(['vehicle' => $vehicle]);

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        self::assertCount(1, $data['organizations_owned'][0]['vehicles']);
        $exportedVehicle = $data['organizations_owned'][0]['vehicles'][0];
        self::assertSame('La Panda', $exportedVehicle['name']);
        self::assertCount(1, $exportedVehicle['maintenance']);
    }

    public function testExportIncludesReminders(): void
    {
        // Regressione: serializeReminder chiamava metodi rimossi dall'entità
        // (isRecurring/getRecurringPeriod) → fatal su export con promemoria.
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        ReminderFactory::createOne(['vehicle' => $vehicle, 'organization' => $org, 'description' => 'Revisione']);

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        $reminders = $data['organizations_owned'][0]['vehicles'][0]['reminders'];
        self::assertCount(1, $reminders);
        self::assertSame('Revisione', $reminders[0]['description']);
        self::assertArrayHasKey('due_date', $reminders[0]);
    }

    public function testExportHasMetadataBlock(): void
    {
        $user = UserFactory::createOne();

        $exporter = static::getContainer()->get(GdprExportService::class);
        $data = $exporter->export($user);

        self::assertArrayHasKey('export_meta', $data);
        self::assertSame('json', $data['export_meta']['format']);
        self::assertStringContainsString('Art. 20', $data['export_meta']['gdpr_article']);
    }
}
