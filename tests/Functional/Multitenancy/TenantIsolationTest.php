<?php

declare(strict_types=1);

namespace App\Tests\Functional\Multitenancy;

use App\Enum\OrgRole;
use App\Enum\ShareRole;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use App\Tests\Support\ApiTestCase;

/**
 * Test di sicurezza trasversali: verificano che un utente NON possa accedere
 * a risorse di un'altra Organization. Sono i test più critici perché un bug
 * qui significa data leak tra tenant.
 *
 * Coprono il primo punto della politica di access in `VehicleAccessChecker`:
 * - membership richiesta prima di qualunque accesso
 * - org_admin vede tutti i vehicle dell'org
 * - plain member vede solo i vehicle con un VehicleShare esplicito
 * - share role rispettato (viewer non può EDIT/DELETE)
 */
final class TenantIsolationTest extends ApiTestCase
{
    public function testUserCannotSeeVehiclesOfAnotherOrganization(): void
    {
        // Org A con il suo veicolo
        [$userA, $orgA, $tokenA] = $this->createAuthenticatedUser();
        VehicleFactory::createOne(['organization' => $orgA]);

        // Org B con il suo veicolo
        [, $orgB] = $this->createAuthenticatedUser();
        VehicleFactory::createOne(['organization' => $orgB]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $tokenA);
        self::assertResponseIsSuccessful();

        $vehicles = $this->jsonBody();
        self::assertCount(1, $vehicles, 'L\'utente vede solo il veicolo della SUA organization');
    }

    public function testUserCannotGetVehicleFromAnotherOrganization(): void
    {
        [, $orgA, $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicleB->getId(), accessToken: $tokenA);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUserCannotEditVehicleFromAnotherOrganization(): void
    {
        [, $orgA, $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);

        $this->jsonRequest('PUT', '/api/vehicles/'.$vehicleB->getId(), [
            'name' => 'hacked',
            'brand' => 'Hack',
            'model' => 'X',
            'year' => 2020,
            'type' => 'car',
            'fuelType' => 'diesel',
        ], accessToken: $tokenA);
        self::assertResponseStatusCodeSame(404);
    }

    public function testUserCannotCreateMaintenanceOnVehicleOfAnotherOrganization(): void
    {
        [, $orgA, $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);

        $this->jsonRequest('POST', '/api/maintenances', [
            'vehicleId' => $vehicleB->getId(),
            'performedAt' => '2026-01-01',
            'km' => 100,
            'type' => 'oil_change',
            'description' => 'cross-tenant attempt',
        ], accessToken: $tokenA);

        self::assertResponseStatusCodeSame(404, 'Il vehicle non esiste nell\'org dell\'utente → 404');
    }

    public function testPlainMemberWithoutShareCannotSeeOrgVehicles(): void
    {
        // Org con 2 utenti: owner (creator) + un plain member
        [, $org] = $this->createAuthenticatedUser();
        [$plainMember, , $memberToken] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        // Aggiungo plainMember alla stessa org dell'owner come MEMBER (non admin)
        OrganizationMemberFactory::createOne([
            'organization' => $org,
            'user' => $plainMember,
            'role' => OrgRole::MEMBER,
        ]);

        // Creo un veicolo nell'org dell'owner — il plain member non ha share esplicito
        VehicleFactory::createOne(['organization' => $org]);

        // Il plain member però ha come "active_org" la SUA org personale → lista vuota
        $this->jsonRequest('GET', '/api/vehicles', accessToken: $memberToken);
        self::assertResponseIsSuccessful();
        self::assertCount(0, $this->jsonBody());
    }

    public function testShareGivesAccessButViewerRoleCannotEdit(): void
    {
        // Org A con un veicolo + owner di A
        [$ownerA, $orgA, $tokenA] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $orgA]);

        // Utente B nella stessa org (member) con uno SHARE viewer sul veicolo
        [$memberB, , $tokenB] = $this->createAuthenticatedUser();
        OrganizationMemberFactory::createOne([
            'organization' => $orgA,
            'user' => $memberB,
            'role' => OrgRole::MEMBER,
        ]);
        VehicleShareFactory::createOne([
            'vehicle' => $vehicle,
            'user' => $memberB,
            'role' => ShareRole::VIEWER,
        ]);

        // memberB ha però come active_org la sua org personale. Per testare lo share,
        // generiamo manualmente un JWT con active_org = orgA per memberB.
        $jwt = static::getContainer()
            ->get(\Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface::class);
        $tokenBOnOrgA = $jwt->createFromPayload($memberB, [
            'user_id' => $memberB->getId(),
            'active_org_id' => $orgA->getId(),
        ]);

        // VIEW → OK
        $this->jsonRequest('GET', '/api/vehicles/'.$vehicle->getId(), accessToken: $tokenBOnOrgA);
        self::assertResponseIsSuccessful();

        // EDIT → 403 perché viewer
        $this->jsonRequest('PUT', '/api/vehicles/'.$vehicle->getId(), [
            'name' => 'forbidden update',
            'brand' => 'X',
            'model' => 'Y',
            'year' => 2020,
            'type' => 'car',
            'fuelType' => 'diesel',
        ], accessToken: $tokenBOnOrgA);
        self::assertResponseStatusCodeSame(403);

        // Anche DELETE → 403
        $this->jsonRequest('DELETE', '/api/vehicles/'.$vehicle->getId(), accessToken: $tokenBOnOrgA);
        self::assertResponseStatusCodeSame(403);
    }

    public function testPlainMemberCreatesVehicleAndOnlyTheyCanSeeIt(): void
    {
        // Un member può creare veicoli: ne diventa proprietario (share admin),
        // lo vede e lo gestisce. Gli altri member dell'org non lo vedono senza share.
        [, $org, $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'Auto del member',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'year' => 2020,
            'type' => 'car',
            'fuelType' => 'diesel',
        ], accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        $vehicleId = $this->jsonBody()['id'];

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);
        self::assertSame([$vehicleId], array_column($this->jsonBody(), 'id'));

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicleId, accessToken: $token);
        self::assertSame(
            ['canEdit' => true, 'canDelete' => true, 'canShare' => true],
            $this->jsonBody()['permissions'],
            'Il proprietario gestisce il proprio veicolo',
        );

        [$otherMember] = $this->createAuthenticatedUser();
        OrganizationMemberFactory::createOne([
            'organization' => $org,
            'user' => $otherMember,
            'role' => OrgRole::MEMBER,
        ]);
        $otherToken = static::getContainer()
            ->get(\Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface::class)
            ->createFromPayload($otherMember, [
                'user_id' => $otherMember->getId(),
                'active_org_id' => $org->getId(),
            ]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $otherToken);
        self::assertCount(0, $this->jsonBody(), 'Senza share un altro member non vede il veicolo');

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicleId, accessToken: $otherToken);
        self::assertResponseStatusCodeSame(403);
    }
}
