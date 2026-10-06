<?php

declare(strict_types=1);

namespace App\Tests\Functional\Command;

use App\Entity\EmailVerificationToken;
use App\Entity\OrganizationInvitation;
use App\Entity\PasswordResetToken;
use App\Entity\RefreshToken;
use App\Entity\User;
use App\Enum\OrgRole;
use App\Tests\Factory\OrganizationFactory;
use App\Tests\Factory\UserFactory;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * `app:refresh-tokens:cleanup` gira da cron (Ofelia) e CANCELLA righe: l'errore da prevenire è
 * portarsi via una sessione ancora valida (o un reset/invito ancora utilizzabile). Il test fissa
 * il confine: sparisce solo ciò che è scaduto/revocato PRIMA della soglia, il resto resta.
 */
final class CleanupRefreshTokensCommandTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private EntityManagerInterface $em;
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
        $this->user = UserFactory::createOne();
    }

    /** @param array<string, string> $input */
    private function runCleanup(array $input = []): CommandTester
    {
        $tester = new CommandTester((new Application(self::bootKernel()))->find('app:refresh-tokens:cleanup'));
        $tester->execute($input);

        return $tester;
    }

    private function refreshToken(string $hash, string $expires, ?string $revoked = null, bool $rotated = false): void
    {
        $token = new RefreshToken($this->user, $hash, new \DateTimeImmutable($expires));
        $this->em->persist($token);
        $this->em->flush();
        if ($revoked !== null) {
            // revoke() usa "adesso": la data di revoca vecchia si imposta direttamente in DB
            $this->em->getConnection()->executeStatement(
                'UPDATE refresh_tokens SET revoked_at = ?, rotated_at = ? WHERE token = ?',
                [(new \DateTimeImmutable($revoked))->format('Y-m-d H:i:s'), $rotated ? (new \DateTimeImmutable($revoked))->format('Y-m-d H:i:s') : null, $hash],
            );
        }
    }

    /** @return list<string> */
    private function remainingRefreshHashes(): array
    {
        $hashes = $this->em->getConnection()->fetchFirstColumn('SELECT token FROM refresh_tokens ORDER BY token');

        return array_map('strval', $hashes);
    }

    public function testDeletesOnlyTokensExpiredOrRevokedBeforeTheCutoff(): void
    {
        $this->refreshToken('active', '+5 days');
        $this->refreshToken('expired-long-ago', '-40 days');
        $this->refreshToken('expired-recently', '-10 days');
        $this->refreshToken('revoked-long-ago', '+5 days', '-40 days');
        $this->refreshToken('revoked-yesterday', '+5 days', '-1 day');

        $tester = $this->runCleanup();

        self::assertSame(Command::SUCCESS, $tester->getStatusCode());
        self::assertSame(['active', 'expired-recently', 'revoked-yesterday'], $this->remainingRefreshHashes());
        self::assertStringContainsString('Cancellati 2 refresh token', $tester->getDisplay());
    }

    /**
     * Un token ruotato serve a riconoscere il riuso finché potrebbe essere ripresentato, cioè finché non
     * scade: la soglia di revoca da sola (qui 1 giorno) non basta a cancellarlo. Uno revocato per logout,
     * che non può più causare nulla, va via come prima.
     */
    public function testRotatedTokenSurvivesUntilItExpiresButLoggedOutOneDoesNot(): void
    {
        $this->refreshToken('rotated-still-valid', '+3 days', '-2 days', rotated: true);
        $this->refreshToken('logged-out-same-age', '+3 days', '-2 days');
        $this->refreshToken('rotated-expired-and-old', '-12 hours', '-8 days', rotated: true);
        $this->refreshToken('rotated-expired-recently', '-12 hours', '-12 hours', rotated: true);

        $this->runCleanup(['--days' => '1']);

        self::assertSame(['rotated-expired-recently', 'rotated-still-valid'], $this->remainingRefreshHashes());
    }

    public function testDaysOptionMovesTheCutoff(): void
    {
        $this->refreshToken('active', '+5 days');
        $this->refreshToken('expired-10-days-ago', '-10 days');
        $this->refreshToken('expired-3-days-ago', '-3 days');

        $this->runCleanup(['--days' => '7']);

        self::assertSame(['active', 'expired-3-days-ago'], $this->remainingRefreshHashes());
    }

    #[DataProvider('invalidDays')]
    public function testInvalidDaysFailsWithoutDeletingAnything(string $days): void
    {
        // Con --days=0 il cutoff sarebbe "adesso": porterebbe via anche le sessioni appena scadute.
        $this->refreshToken('expired-long-ago', '-40 days');

        $tester = $this->runCleanup(['--days' => $days]);

        self::assertSame(Command::INVALID, $tester->getStatusCode());
        self::assertSame(['expired-long-ago'], $this->remainingRefreshHashes());
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDays(): iterable
    {
        yield 'zero' => ['0'];
        yield 'negative' => ['-5'];
        yield 'not a number' => ['abc'];
    }

    public function testResetVerificationTokensAndInvitationsAreCleanedWithTheSameCutoff(): void
    {
        $org = OrganizationFactory::createOne();
        $old = new \DateTimeImmutable('-40 days');
        $recent = new \DateTimeImmutable('-2 days');
        $valid = new \DateTimeImmutable('+2 days');

        foreach (['old' => $old, 'recent' => $recent, 'valid' => $valid] as $label => $expires) {
            $this->em->persist(new PasswordResetToken($this->user, "reset-$label", $expires));
            $this->em->persist(new EmailVerificationToken($this->user, "verify-$label", $expires));
            $this->em->persist(new OrganizationInvitation($org, "$label@test.it", OrgRole::MEMBER, "invite-$label", $expires));
        }
        $this->em->flush();

        $tester = $this->runCleanup();

        $connection = $this->em->getConnection();
        self::assertSame(['reset-recent', 'reset-valid'], array_map('strval', $connection->fetchFirstColumn('SELECT token_hash FROM password_reset_tokens ORDER BY token_hash')));
        self::assertSame(['verify-recent', 'verify-valid'], array_map('strval', $connection->fetchFirstColumn('SELECT token_hash FROM email_verification_tokens ORDER BY token_hash')));
        self::assertSame(['invite-recent', 'invite-valid'], array_map('strval', $connection->fetchFirstColumn('SELECT token_hash FROM organization_invitations ORDER BY token_hash')));
        self::assertStringContainsString('Cancellati 3 token di reset/verifica', $tester->getDisplay());
    }

    public function testRunningTwiceIsIdempotent(): void
    {
        $this->refreshToken('active', '+5 days');
        $this->refreshToken('expired-long-ago', '-40 days');

        $this->runCleanup();
        $second = $this->runCleanup();

        self::assertSame(Command::SUCCESS, $second->getStatusCode());
        self::assertSame(['active'], $this->remainingRefreshHashes());
        self::assertStringContainsString('Cancellati 0 refresh token', $second->getDisplay());
    }
}
