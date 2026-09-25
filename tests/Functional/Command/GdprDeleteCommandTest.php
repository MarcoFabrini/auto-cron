<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\User;
use App\Enum\OrgRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test del comando CLI `app:gdpr:delete` — la logica GDPR vera è in
 * GdprDeleteService (già testata a parte); qui si copre il wiring CLI:
 * argomento email, dry-run di default, --confirm, exit code sugli errori.
 */
final class GdprDeleteCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private function tester(): CommandTester
    {
        $application = new Application(self::bootKernel());
        return new CommandTester($application->find('app:gdpr:delete'));
    }

    private function em(): EntityManagerInterface
    {
        return static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testFailsWhenUserNotFound(): void
    {
        $tester = $this->tester();
        $tester->execute(['email' => 'nobody@example.com']);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('not found', $tester->getDisplay());
    }

    public function testDryRunByDefaultMakesNoChanges(): void
    {
        $user = UserFactory::createOne(['email' => 'keep@test.it']);
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);

        $tester = $this->tester();
        $tester->execute(['email' => 'keep@test.it']);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('DRY-RUN', $tester->getDisplay());

        $this->em()->clear();
        $refreshed = $this->em()->find(User::class, $user->getId());
        self::assertNotNull($refreshed);
        self::assertSame('keep@test.it', $refreshed->getEmail(), 'Senza --confirm il dry-run non deve modificare nulla');
    }

    public function testConfirmActuallyAnonymizesUser(): void
    {
        $user = UserFactory::createOne(['email' => 'erase@test.it']);
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $user, 'organization' => $org, 'role' => OrgRole::OWNER]);
        $userId = $user->getId();

        $tester = $this->tester();
        $tester->execute(['email' => 'erase@test.it', '--confirm' => true]);

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertStringContainsString('anonymized', $tester->getDisplay());

        $this->em()->clear();
        $refreshed = $this->em()->find(User::class, $userId);
        self::assertNotNull($refreshed);
        self::assertStringEndsWith('@anonymized.local', $refreshed->getEmail());
    }

    public function testConfirmFailsWithErrorWhenDeletionBlocked(): void
    {
        // Owner unico di un'org con altri membri attivi: GdprDeleteService blocca.
        $owner = UserFactory::createOne(['email' => 'blocked-owner@test.it']);
        $member = UserFactory::createOne();
        $org = OrganizationFactory::createOne();
        OrganizationMemberFactory::createOne(['user' => $owner, 'organization' => $org, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['user' => $member, 'organization' => $org, 'role' => OrgRole::MEMBER]);

        $tester = $this->tester();
        $tester->execute(['email' => 'blocked-owner@test.it', '--confirm' => true]);

        self::assertSame(Command::FAILURE, $tester->getStatusCode());
        self::assertStringContainsString('Deletion blocked', $tester->getDisplay());

        $this->em()->clear();
        $refreshed = $this->em()->find(User::class, $owner->getId());
        self::assertNotNull($refreshed);
        self::assertSame('blocked-owner@test.it', $refreshed->getEmail(), 'Il blocco deve impedire ogni modifica');
    }
}
