<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\EmailVerificationTokenRepository;
use App\Repository\OrganizationInvitationRepository;
use App\Repository\PasswordResetTokenRepository;
use App\Repository\RefreshTokenRepository;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Hard-delete dei refresh token expired o revoked più vecchi di N giorni.
 * Da schedulare con cron settimanale (i token expired non danno problemi di sicurezza,
 * ma occupano spazio inutile).
 *
 *   0 3 * * 0  php bin/console app:refresh-tokens:cleanup
 */
#[AsCommand(name: 'app:refresh-tokens:cleanup', description: 'Hard-delete expired/revoked refresh tokens')]
final class CleanupRefreshTokensCommand extends Command
{
    public function __construct(
        private readonly RefreshTokenRepository $repo,
        private readonly PasswordResetTokenRepository $resetTokens,
        private readonly EmailVerificationTokenRepository $verificationTokens,
        private readonly OrganizationInvitationRepository $invitations,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('days', null, InputOption::VALUE_REQUIRED, 'Delete tokens older than N days', '30');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int) $input->getOption('days');
        if ($days < 1) {
            $io->error('--days deve essere un intero >= 1.');

            return Command::INVALID;
        }
        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days");

        $deleted = $this->repo->deleteExpiredAndRevoked($cutoff);
        $io->success("Cancellati $deleted refresh token più vecchi di $days giorni.");

        // Anche token di reset/verifica e inviti scaduti: altrimenti le tabelle crescono all'infinito
        // e le email degli invitati restano salvate senza motivo.
        $others = $this->resetTokens->deleteExpired($cutoff)
            + $this->verificationTokens->deleteExpired($cutoff)
            + $this->invitations->deleteExpired($cutoff);
        $io->success("Cancellati $others token di reset/verifica e inviti scaduti.");

        return Command::SUCCESS;
    }
}
