<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Enum\OrgRole;
use App\Repository\OrganizationRepository;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Support\ApiTestCase;
use Symfony\Bundle\FrameworkBundle\Test\MailerAssertionsTrait;
use Symfony\Component\Mime\Email;

final class OrganizationControllerTest extends ApiTestCase
{
    use MailerAssertionsTrait;

    public function testListReturnsOnlyUserOrganizations(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();

        // Crea un'altra org dove l'utente NON è membro
        OrganizationFactory::createOne();

        $this->jsonRequest('GET', '/api/organizations', accessToken: $token);

        self::assertResponseIsSuccessful();
        /** @var list<array<string, mixed>> $orgs */
        $orgs = $this->jsonBody();
        self::assertCount(1, $orgs);
        self::assertSame($org->getId(), $orgs[0]['id']);
    }

    public function testCreateOrganizationMakesUserOwner(): void
    {
        [$user, , $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/organizations', [
            'name' => 'Famiglia Rossi',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Famiglia Rossi', $this->jsonBody()['name']);
        self::assertNotEmpty($this->jsonBody()['slug']);

        // Tramite /me l'utente deve avere ora 2 memberships
        $this->jsonRequest('GET', '/api/auth/me', accessToken: $token);
        self::assertCount(2, $this->jsonBody()['memberships']);
    }

    public function testGetReturnsForbiddenForNonMember(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        $other = OrganizationFactory::createOne();

        $this->jsonRequest('GET', '/api/organizations/'.$other->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateRequiresAdminRole(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        $this->jsonRequest('PUT', '/api/organizations/'.$org->getId(), [
            'name' => 'Updated',
        ], accessToken: $token);
        self::assertResponseStatusCodeSame(403);
    }

    public function testUpdateAsOwner(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('PUT', '/api/organizations/'.$org->getId(), [
            'name' => 'New Name',
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('New Name', $this->jsonBody()['name']);
    }

    public function testDeleteRequiresOwner(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser(OrgRole::ADMIN);

        $this->jsonRequest('DELETE', '/api/organizations/'.$org->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(403, 'Solo owner può cancellare org');
    }

    public function testDeleteAsOwner(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $id = $org->getId();

        $this->jsonRequest('DELETE', '/api/organizations/'.$id, accessToken: $token);
        self::assertResponseStatusCodeSame(204);

        $repo = static::getContainer()->get(OrganizationRepository::class);
        self::assertNull($repo->find($id));
    }

    // ----- Members -----

    public function testInviteEmailsAcceptLinkAndListsPending(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/organizations/'.$org->getId().'/members', [
            'email' => 'Newcomer@Test.it',
            'role' => OrgRole::MEMBER,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('newcomer@test.it', $this->jsonBody()['email'], 'Email normalizzata lowercase');
        self::assertSame('member', $this->jsonBody()['role']);

        self::assertEmailCount(1);
        $message = self::getMailerMessage();
        self::assertInstanceOf(Email::class, $message);
        self::assertSame(1, preg_match('/accept-invite\?token=[a-f0-9]+/', $message->toString()), 'Email con link di accettazione');

        $this->jsonRequest('GET', '/api/organizations/'.$org->getId().'/invitations', accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonBody());
    }

    public function testInviteNonRegisteredEmailSucceeds(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/organizations/'.$org->getId().'/members', [
            'email' => 'ghost@nowhere.test',
            'role' => 'member',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201, 'Si può invitare anche chi non ha ancora un account');
    }

    public function testListMembersIncludesUserInfo(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('GET', '/api/organizations/'.$org->getId().'/members', accessToken: $token);

        self::assertResponseIsSuccessful();
        $body = $this->jsonBody();
        self::assertNotEmpty($body);
        $first = reset($body);
        self::assertIsArray($first);
        self::assertArrayHasKey('user', $first, 'La lista membri deve esporre l\'utente');
        self::assertIsArray($first['user']);
        self::assertArrayHasKey('email', $first['user']);
    }

    public function testListMembersRequiresAdminRole(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);

        $this->jsonRequest('GET', '/api/organizations/'.$org->getId().'/members', accessToken: $token);
        self::assertResponseStatusCodeSame(403, 'Un membro non vede la lista utenti dell\'org');
    }

    public function testReInviteSameEmailResendsAndKeepsOnePending(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $invite = fn () => $this->jsonRequest('POST', '/api/organizations/'.$org->getId().'/members', [
            'email' => 'pending@test.it',
            'role' => 'member',
        ], accessToken: $token);

        $invite();
        self::assertResponseStatusCodeSame(201);
        $invite();
        self::assertResponseStatusCodeSame(201, 'Re-invito rimanda, non 409');

        $this->jsonRequest('GET', '/api/organizations/'.$org->getId().'/invitations', accessToken: $token);
        self::assertCount(1, $this->jsonBody(), 'Un solo invito pendente per email');
    }

    public function testRevokeInvitation(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('POST', '/api/organizations/'.$org->getId().'/members', [
            'email' => 'revoke@test.it',
            'role' => 'member',
        ], accessToken: $token);
        self::assertResponseStatusCodeSame(201);
        $invId = $this->jsonBody()['id'];
        self::assertIsInt($invId);

        $this->jsonRequest('DELETE', '/api/organizations/'.$org->getId().'/invitations/'.$invId, accessToken: $token);
        self::assertResponseStatusCodeSame(204);

        $this->jsonRequest('GET', '/api/organizations/'.$org->getId().'/invitations', accessToken: $token);
        self::assertCount(0, $this->jsonBody());
    }

    public function testInviteAlreadyMemberReturns409(): void
    {
        [$owner, $org, $token] = $this->createAuthenticatedUser();
        $existing = UserFactory::createOne(['email' => 'already@test.it']);
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $existing]);

        $this->jsonRequest('POST', '/api/organizations/'.$org->getId().'/members', [
            'email' => 'already@test.it',
            'role' => 'member',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(409);
    }

    public function testCannotRemoveOwner(): void
    {
        [$owner, $org, $token] = $this->createAuthenticatedUser();
        $ownerMembership = static::getContainer()
            ->get(\App\Repository\OrganizationMemberRepository::class)
            ->findMembership($owner, $org);
        self::assertNotNull($ownerMembership);

        $this->jsonRequest('DELETE', '/api/organizations/'.$org->getId().'/members/'.$ownerMembership->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(409);
    }

    // ----- switch-org -----

    public function testSwitchOrgIssuesNewToken(): void
    {
        [$user, , $token] = $this->createAuthenticatedUser();
        // Aggiungo all'utente una seconda org
        $org2 = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne([
            'organization' => $org2,
            'user' => $user,
            'role' => OrgRole::OWNER,
        ]);

        $this->jsonRequest('POST', '/api/auth/switch-org', [
            'organizationId' => $org2->getId(),
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        $body = $this->jsonBody();
        self::assertArrayHasKey('access_token', $body);
        self::assertSame($org2->getId(), $body['active_org_id']);

        // Decodifica il nuovo JWT e verifica che active_org_id sia cambiato
        $parts = explode('.', $body['access_token']);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/')), true);
        self::assertSame($org2->getId(), $payload['active_org_id']);
    }

    public function testSwitchOrgRefusesNonMember(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        $other = OrganizationFactory::createOne();

        $this->jsonRequest('POST', '/api/auth/switch-org', [
            'organizationId' => $other->getId(),
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(403);
    }
}
