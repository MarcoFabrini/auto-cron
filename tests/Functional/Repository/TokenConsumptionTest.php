<?php

declare(strict_types=1);

namespace App\Tests\Functional\Repository;

use App\Entity\EmailVerificationToken;
use App\Entity\OrganizationInvitation;
use App\Entity\PasswordResetToken;
use App\Enum\OrgRole;
use App\Repository\EmailVerificationTokenRepository;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * I token monouso si consumano con un UPDATE condizionale: di due richieste concorrenti con lo stesso
 * token ne passa una sola (prima: find + markUsed + flush, non atomico).
 */
final class TokenConsumptionTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    public function testPasswordResetTokenCanBeConsumedOnlyOnce(): void
    {
        $token = new PasswordResetToken(UserFactory::createOne(), hash('sha256', 'reset'), new \DateTimeImmutable('+1 hour'));
        $this->persist($token);
        $repo = static::getContainer()->get(PasswordResetTokenRepository::class);

        self::assertTrue($repo->consume($token));
        self::assertFalse($repo->consume($token), 'la seconda richiesta concorrente deve perdere');
    }

    public function testEmailVerificationTokenCanBeConsumedOnlyOnce(): void
    {
        $token = new EmailVerificationToken(UserFactory::createOne(), hash('sha256', 'verify'), new \DateTimeImmutable('+1 hour'));
        $this->persist($token);
        $repo = static::getContainer()->get(EmailVerificationTokenRepository::class);

        self::assertTrue($repo->consume($token));
        self::assertFalse($repo->consume($token));
    }

    public function testInvitationCanBeConsumedOnlyOnce(): void
    {
        $invitation = new OrganizationInvitation(
            OrganizationFactory::createOne(),
            'nuovo@test.it',
            OrgRole::MEMBER,
            hash('sha256', 'invite'),
            new \DateTimeImmutable('+7 days'),
        );
        $this->persist($invitation);
        $repo = static::getContainer()->get(OrganizationInvitationRepository::class);

        self::assertTrue($repo->consume($invitation));
        self::assertFalse($repo->consume($invitation));
    }

    private function persist(object $entity): void
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->persist($entity);
        $em->flush();
    }
}
