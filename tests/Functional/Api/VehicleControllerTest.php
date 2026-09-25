<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\OrgRole;
use App\Enum\ShareRole;
use App\Repository\VehicleRepository;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use App\Tests\Support\ApiTestCase;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

final class VehicleControllerTest extends ApiTestCase
{
    public function testListReturnsOnlyVehiclesInActiveOrganization(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        VehicleFactory::createMany(3, ['organization' => $org]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(3, $this->jsonBody());
    }

    public function testListExcludesArchivedVehiclesByDefault(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        VehicleFactory::createOne(['organization' => $org]);
        VehicleFactory::createOne(['organization' => $org, 'archivedAt' => new \DateTimeImmutable()]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);
        self::assertCount(1, $this->jsonBody());
    }

    public function testGetReturnsVehicleDetail(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org, 'name' => 'La Golf']);

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicle->getId(), accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('La Golf', $this->jsonBody()['name']);
    }

    public function testGetNonExistentReturns404(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        $this->jsonRequest('GET', '/api/vehicles/999999', accessToken: $token);
        self::assertResponseStatusCodeSame(404);
    }

    public function testCreateAsOrgOwnerPersistsVehicle(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'La Panda',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'year' => 2019,
            'type' => 'car',
            'fuelType' => 'gasoline',
            'initialKm' => 30000,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('La Panda', $this->jsonBody()['name']);

        $repo = static::getContainer()->get(VehicleRepository::class);
        self::assertCount(1, $repo->findAll());
    }

    public function testCreateValidatesRequiredFields(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => '',
            'brand' => '',
            'model' => '',
            'year' => 1500,  // before 1900 → invalid
            'type' => 'car',
            'fuelType' => 'gasoline',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testUpdateChangesFields(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org, 'name' => 'old']);

        $this->jsonRequest('PUT', '/api/vehicles/'.$vehicle->getId(), [
            'name' => 'new name',
            'brand' => 'BMW',
            'model' => 'X1',
            'year' => 2023,
            'type' => 'car',
            'fuelType' => 'hybrid',
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('new name', $this->jsonBody()['name']);
    }

    public function testDeleteRemovesVehicle(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $id = $vehicle->getId();  // salva l'id prima della delete (il proxy lo dimentica)

        $this->jsonRequest('DELETE', '/api/vehicles/'.$id, accessToken: $token);
        self::assertResponseStatusCodeSame(204);

        $repo = static::getContainer()->get(VehicleRepository::class);
        self::assertNull($repo->find($id));
    }

    public function testArchiveSetsArchivedAt(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/archive', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->jsonBody()['archivedAt']);
    }

    /** Membro accettato (ruolo member) dell'org, con nome e cognome noti. */
    private function memberOf(Organization $org, string $first, string $last, OrgRole $role = OrgRole::MEMBER): User
    {
        $user = UserFactory::createOne(['firstName' => $first, 'lastName' => $last]);
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $user, 'role' => $role]);

        return $user;
    }

    /** @return list<array<string, mixed>> */
    private function shares(Vehicle $vehicle, string $token): array
    {
        $this->jsonRequest('GET', '/api/vehicles/'.$vehicle->getId().'/shares', accessToken: $token);

        return array_values($this->jsonBody());
    }

    public function testCreateShareIsAlwaysReadOnly(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $invitee = $this->memberOf($org, 'Anna', 'Bianchi');

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', [
            'userIds' => [$invitee->getId()],
            'role' => ShareRole::EDITOR->value, // ignorato: gli share sono sempre in sola lettura
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonBody();
        self::assertCount(1, $body);
        $created = reset($body);
        self::assertIsArray($created);
        self::assertSame('viewer', $created['role']);
        self::assertSame('Anna', $created['user']['firstName'], 'La risposta espone l\'utente condiviso');
        self::assertSame('Bianchi', $created['user']['lastName']);
    }

    public function testShareWithSeveralMembersAtOnce(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $anna = $this->memberOf($org, 'Anna', 'Bianchi');
        $carlo = $this->memberOf($org, 'Carlo', 'Alberti');

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', [
            'userIds' => [$anna->getId(), $carlo->getId()],
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertCount(2, $this->jsonBody());
        self::assertCount(2, $this->shares($vehicle, $token));
    }

    public function testShareIsIdempotentForAlreadySharedMembers(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $anna = $this->memberOf($org, 'Anna', 'Bianchi');
        $payload = ['userIds' => [$anna->getId()]];

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', $payload, accessToken: $token);
        self::assertResponseStatusCodeSame(201);

        // Secondo invio (doppio click, richiesta ripetuta): nessun errore, nessun duplicato.
        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', $payload, accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        self::assertSame([], $this->jsonBody(), 'Niente di nuovo da creare');
        self::assertCount(1, $this->shares($vehicle, $token));
    }

    public function testUnknownAndOutsiderIdsAreIndistinguishable(): void
    {
        // Senza email digitata non c'è modo di sondare quali account esistono:
        // id inesistente e id di un utente reale ma estraneo all'org → stessa risposta.
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $outsider = UserFactory::createOne();
        $url = '/api/vehicles/'.$vehicle->getId().'/shares';

        $this->jsonRequest('POST', $url, ['userIds' => [$outsider->getId()]], accessToken: $token);
        $outsiderStatus = $this->client->getResponse()->getStatusCode();
        $outsiderBody = $this->jsonBody();

        $this->jsonRequest('POST', $url, ['userIds' => [99999999]], accessToken: $token);

        self::assertSame(422, $outsiderStatus);
        self::assertSame('share.not_org_member', $outsiderBody['title']);
        self::assertResponseStatusCodeSame(422);
        self::assertSame($outsiderBody, $this->jsonBody());
    }

    public function testCannotShareWithOrgAdminSelfOrPendingMember(): void
    {
        [$me, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $admin = $this->memberOf($org, 'Ada', 'Admin', OrgRole::ADMIN);
        $pending = UserFactory::createOne();
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $pending, 'acceptedAt' => null]);
        $url = '/api/vehicles/'.$vehicle->getId().'/shares';

        foreach ([$admin->getId(), $me->getId(), $pending->getId()] as $id) {
            $this->jsonRequest('POST', $url, ['userIds' => [$id]], accessToken: $token);
            self::assertResponseStatusCodeSame(422);
            self::assertSame('share.not_org_member', $this->jsonBody()['title']);
        }
        self::assertSame([], $this->shares($vehicle, $token));
    }

    public function testShareIsAtomicWhenOneIdIsInvalid(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $anna = $this->memberOf($org, 'Anna', 'Bianchi');

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', [
            'userIds' => [$anna->getId(), 99999999],
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([], $this->shares($vehicle, $token), 'Nessuno share parziale');
    }

    public function testShareRejectsMissingOrMalformedUserIds(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $url = '/api/vehicles/'.$vehicle->getId().'/shares';

        foreach ([[], ['userIds' => []], ['userIds' => ['anna@test.it']], ['userIds' => [0]], ['userEmail' => 'anna@test.it']] as $body) {
            $this->jsonRequest('POST', $url, $body, accessToken: $token);
            self::assertResponseStatusCodeSame(422);
        }
    }

    public function testShareCandidatesListsOnlyShareableMembersByName(): void
    {
        [$me, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $anna = $this->memberOf($org, 'Anna', 'Bianchi');
        $carlo = $this->memberOf($org, 'Carlo', 'Alberti');
        $this->memberOf($org, 'Ada', 'Admin', OrgRole::ADMIN);          // vede già tutto
        $shared = $this->memberOf($org, 'Sara', 'Condivisa');            // già condiviso
        VehicleShareFactory::createOne(['vehicle' => $vehicle, 'user' => $shared, 'role' => ShareRole::VIEWER]);
        $pending = UserFactory::createOne();                    // invito non accettato
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $pending, 'acceptedAt' => null]);
        UserFactory::createOne();                                        // estraneo all'org

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicle->getId().'/share-candidates', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame(
            [
                ['id' => $carlo->getId(), 'firstName' => 'Carlo', 'lastName' => 'Alberti'],
                ['id' => $anna->getId(), 'firstName' => 'Anna', 'lastName' => 'Bianchi'],
            ],
            $this->jsonBody(),
            'Solo member accettati non ancora condivisi, per cognome, e senza email',
        );
        self::assertNotContains($me->getId(), array_column($this->jsonBody(), 'id'), 'Non include chi condivide');
    }

    public function testMemberWhoOwnsAVehicleCanListCandidates(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);
        $other = $this->memberOf($org, 'Luca', 'Verdi');

        $this->jsonRequest('POST', '/api/vehicles', $this->vehiclePayload(), accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonBody()['id'];

        $this->jsonRequest('GET', '/api/vehicles/'.$id.'/share-candidates', accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertSame([$other->getId()], array_column($this->jsonBody(), 'id'));
    }

    public function testShareCandidatesRequireEditPermissionAndOrgScope(): void
    {
        [, $org] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $viewer = $this->memberOf($org, 'Vera', 'Lettrice');
        VehicleShareFactory::createOne(['vehicle' => $vehicle, 'user' => $viewer, 'role' => ShareRole::VIEWER]);
        $url = '/api/vehicles/'.$vehicle->getId().'/share-candidates';

        // Chi ha solo lo share in lettura non può vedere né gestire le condivisioni.
        $this->jsonRequest('GET', $url, accessToken: $this->tokenForOrg($viewer, $org));
        self::assertResponseStatusCodeSame(403);

        // Un'altra organizzazione non vede nemmeno che il veicolo esiste.
        [, , $foreignToken] = $this->createAuthenticatedUser();
        $this->jsonRequest('GET', $url, accessToken: $foreignToken);
        self::assertResponseStatusCodeSame(404);

        $this->jsonRequest('GET', $url);
        self::assertResponseStatusCodeSame(401);
    }

    public function testOrgAdminCreatingVehicleGetsNoOwnerShare(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', $this->vehiclePayload(), accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonBody()['id'];

        $this->jsonRequest('GET', '/api/vehicles/'.$id.'/shares', accessToken: $token);
        self::assertSame([], $this->jsonBody(), 'Owner/admin ha già accesso totale via ruolo org');
    }

    public function testMemberCreatingVehicleBecomesOwnerAndCannotRevokeIt(): void
    {
        [$member, , $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        $this->jsonRequest('POST', '/api/vehicles', $this->vehiclePayload(), accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        $id = $this->jsonBody()['id'];

        $this->jsonRequest('GET', '/api/vehicles/'.$id.'/shares', accessToken: $token);
        $shares = $this->jsonBody();
        self::assertCount(1, $shares);
        $ownerShare = reset($shares);
        self::assertIsArray($ownerShare);
        self::assertSame('admin', $ownerShare['role']);
        self::assertSame($member->getId(), $ownerShare['user']['id']);

        $this->jsonRequest('DELETE', '/api/vehicles/'.$id.'/shares/'.$ownerShare['id'], accessToken: $token);
        self::assertResponseStatusCodeSame(409);
        self::assertSame('share.cannot_remove_owner', $this->jsonBody()['title']);
    }

    public function testViewerShareGetsReadOnlyPermissions(): void
    {
        [, $org] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        [$viewer] = $this->createAuthenticatedUser();
        OrganizationMemberFactory::createOne([
            'organization' => $org,
            'user' => $viewer,
            'role' => OrgRole::MEMBER,
        ]);
        VehicleShareFactory::createOne([
            'vehicle' => $vehicle,
            'user' => $viewer,
            'role' => ShareRole::VIEWER,
        ]);
        $viewerToken = $this->tokenForOrg($viewer, $org);

        $this->jsonRequest('GET', '/api/vehicles/'.$vehicle->getId(), accessToken: $viewerToken);
        self::assertResponseIsSuccessful();
        self::assertSame(
            ['canEdit' => false, 'canDelete' => false, 'canShare' => false],
            $this->jsonBody()['permissions'],
        );

        $this->jsonRequest('PUT', '/api/vehicles/'.$vehicle->getId(), $this->vehiclePayload(), accessToken: $viewerToken);
        self::assertResponseStatusCodeSame(403);

        $this->jsonRequest('POST', '/api/vehicles/'.$vehicle->getId().'/shares', [
            'userIds' => [$viewer->getId()],
        ], accessToken: $viewerToken);
        self::assertResponseStatusCodeSame(403, 'Chi riceve uno share non può ricondividere');
    }

    public function testUnauthenticatedRequestReturns401(): void
    {
        $this->jsonRequest('GET', '/api/vehicles');
        self::assertResponseStatusCodeSame(401);
    }

    // ----- Bi-fuel -----

    public function testCreateBiFuelVehicle(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'La Punto GPL',
            'brand' => 'Fiat',
            'model' => 'Punto',
            'year' => 2018,
            'type' => 'car',
            'fuelType' => 'gasoline',
            'secondaryFuelType' => 'lpg',  // bi-fuel benzina + GPL
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        $body = $this->jsonBody();
        self::assertSame('gasoline', $body['fuelType']);
        self::assertSame('lpg', $body['secondaryFuelType']);
    }

    public function testCreateHybridVehicle(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'La Auris',
            'brand' => 'Toyota',
            'model' => 'Auris Hybrid',
            'year' => 2022,
            'type' => 'car',
            'fuelType' => 'gasoline',
            'secondaryFuelType' => 'electric',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('electric', $this->jsonBody()['secondaryFuelType']);
    }

    public function testBiFuelRejectsDuplicateFuelTypes(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'Invalida',
            'brand' => 'X', 'model' => 'Y', 'year' => 2020,
            'type' => 'car',
            'fuelType' => 'gasoline',
            'secondaryFuelType' => 'gasoline',  // duplicato → 422
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCreateMonoFuelVehicle(): void
    {
        [, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/vehicles', [
            'name' => 'La Diesel',
            'brand' => 'BMW', 'model' => '320d', 'year' => 2020,
            'type' => 'car',
            'fuelType' => 'diesel',
            // secondaryFuelType non passato → null
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertNull($this->jsonBody()['secondaryFuelType']);
    }

    // ----- helpers -----

    /**
     * @return array<string, mixed>
     */
    private function vehiclePayload(): array
    {
        return [
            'name' => 'Panda di casa',
            'brand' => 'Fiat',
            'model' => 'Panda',
            'year' => 2020,
            'type' => 'car',
            'fuelType' => 'diesel',
        ];
    }

    /** JWT con `active_org_id` = $org (per un utente aggiunto a un'org non sua). */
    private function tokenForOrg(User $user, Organization $org): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->createFromPayload($user, [
            'user_id' => $user->getId(),
            'active_org_id' => $org->getId(),
        ]);
    }
}
