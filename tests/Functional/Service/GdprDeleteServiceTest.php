<?php

declare(strict_types=1);

namespace App\Tests\Functional\Service;

use App\Entity\PushSubscription;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Enum\PushPlatform;
use App\Service\Gdpr\GdprDeleteBlockedException;
use App\Service\Gdpr\GdprDeleteService;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test GDPR Art. 17 right-to-erasure (#5.5).
 */
final class GdprDeleteServiceTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private function service(): GdprDeleteService
    {
        return static::getContainer()->get(GdprDeleteService::class);
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testAnonymizeOverwritesPiiFields(): void
    {
        $user = UserFactory::createOne(['email' => 'forget@test.it', 'firstName' => 'Mario', 'lastName' => 'Rossi']);
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $summary = $this->service()->anonymize($user);

        $this->em()->clear();
        $refreshed = $this->em()->find(User::class, $summary['anonymized_user_id']);
        self::assertNotNull($refreshed);
        self::assertStringStartsWith('deleted-', $refreshed->getEmail());
        self::assertStringEndsWith('@anonymized.local', $refreshed->getEmail());
        self::assertSame('Deleted', $refreshed->getFirstName());
        self::assertSame('User', $refreshed->getLastName());
    }

    public function testAnonymizeRevokesRefreshTokens(): void
    {
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $token = new RefreshToken($user, 'tok_'.bin2hex(random_bytes(8)), new \DateTimeImmutable('+1 day'));
        $this->em()->persist($token);
        $this->em()->flush();

        $summary = $this->service()->anonymize($user);

        self::assertSame(1, $summary['revoked_tokens']);
        $this->em()->refresh($token);
        self::assertNotNull($token->getRevokedAt());
    }

    public function testAnonymizeDeletesPushSubscriptions(): void
    {
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $sub = new PushSubscription();
        $sub->setUser($user)
            ->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://fcm.test/example');
        $this->em()->persist($sub);
        $this->em()->flush();
        $subId = $sub->getId();

        $summary = $this->service()->anonymize($user);

        self::assertSame(1, $summary['deleted_push_subs']);
        $this->em()->clear();
        self::assertNull($this->em()->find(PushSubscription::class, $subId));
    }

    public function testAnonymizeDeletesSoloOwnedOrganization(): void
    {
        $user = UserFactory::createOne();
        $org = OrganizationFactory::createOne(['slug' => 'solo-org']);
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);
        $orgId = $org->getId();

        $summary = $this->service()->anonymize($user);

        self::assertSame(1, $summary['deleted_orgs']);
        self::assertNull($this->em()->find(\App\Entity\Organization::class, $orgId));
    }

    public function testAnonymizeBlockedWhenSoleOwnerWithOtherMembers(): void
    {
        $owner = UserFactory::createOne();
        $member = UserFactory::createOne();
        $org = OrganizationFactory::createOne(['slug' => 'shared-org']);
        OrganizationMemberFactory::createOne(['user' => $owner, 'organization' => $org, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $member, 'organization' => $org, 'role' => OrgRole::MEMBER]);

        $this->expectException(GdprDeleteBlockedException::class);
        $this->service()->anonymize($owner);
    }

    public function testAnonymizeAllowedWhenAnotherOwnerExists(): void
    {
        $owner1 = UserFactory::createOne();
        $owner2 = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $owner1, 'organization' => $org, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $owner2, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $summary = $this->service()->anonymize($owner1);

        self::assertSame(0, $summary['deleted_orgs']);
        self::assertSame(1, $summary['kept_memberships']);
    }
}
