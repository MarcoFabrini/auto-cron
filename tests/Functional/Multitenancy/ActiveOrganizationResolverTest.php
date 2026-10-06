<?php

declare(strict_types=1);

namespace App\Tests\Functional\Multitenancy;

use App\Entity\Organization;
use App\Entity\OrganizationMember;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Support\ApiTestCase;
use Doctrine\ORM\EntityManagerInterface;
use Lexik\Bundle\JWTAuthenticationBundle\Services\JWTTokenManagerInterface;

/**
 * Il claim `active_org_id` del JWT non è una prova di appartenenza: a ogni richiesta
 * ActiveOrganizationResolver verifica che l'utente sia ancora membro (accettato) di
 * quell'organizzazione. Un token con un'org altrui, o di un membro rimosso, non apre nulla.
 */
final class ActiveOrganizationResolverTest extends ApiTestCase
{
    public function testTokenPointingToAnOrganizationTheUserDoesNotBelongToIsForbidden(): void
    {
        [$user] = $this->createAuthenticatedUser();
        [, $foreignOrg] = $this->createAuthenticatedUser();
        VehicleFactory::createOne(['organization' => $foreignOrg]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $this->tokenFor($user, $foreignOrg));

        self::assertResponseStatusCodeSame(403);
        self::assertSame('auth.not_member_of_organization', $this->jsonBody()['title']);
    }

    public function testTokenOfARemovedMemberIsForbidden(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);
        self::assertResponseIsSuccessful('Prima della rimozione il token funziona');

        $this->em()->remove($this->membership($user, $org));
        $this->em()->flush();

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);
        self::assertResponseStatusCodeSame(403);
        self::assertSame('auth.not_member_of_organization', $this->jsonBody()['title']);
    }

    public function testTokenOfAMemberWhoHasNotAcceptedTheInvitationYetIsForbidden(): void
    {
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne([
            'user' => $user,
            'organization' => $org,
            'role' => OrgRole::MEMBER,
            'acceptedAt' => null,
        ]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $this->tokenFor($user, $org));

        self::assertResponseStatusCodeSame(403);
        self::assertSame('auth.not_member_of_organization', $this->jsonBody()['title']);
    }

    public function testTokenPointingToAnUnknownOrganizationIsForbidden(): void
    {
        [$user] = $this->createAuthenticatedUser();
        $token = static::getContainer()->get(JWTTokenManagerInterface::class)->createFromPayload($user, [
            'user_id' => $user->getId(),
            'active_org_id' => 999999,
        ]);

        $this->jsonRequest('GET', '/api/vehicles', accessToken: $token);

        self::assertResponseStatusCodeSame(403);
        self::assertSame('auth.organization_not_found', $this->jsonBody()['title']);
    }

    private function tokenFor(User $user, Organization $org): string
    {
        return static::getContainer()->get(JWTTokenManagerInterface::class)->createFromPayload($user, [
            'user_id' => $user->getId(),
            'active_org_id' => $org->getId(),
        ]);
    }

    private function membership(User $user, Organization $org): OrganizationMember
    {
        $member = OrganizationMemberFactory::repository()->findOneBy(['user' => $user, 'organization' => $org]);
        self::assertNotNull($member);

        return $member;
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }
}
