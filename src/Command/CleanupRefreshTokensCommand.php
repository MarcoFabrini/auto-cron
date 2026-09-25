<?php

declare(strict_types=1);

namespace App\Command;

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
    public function __construct(private readonly RefreshTokenRepository $repo)
    {
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
        $cutoff = (new \DateTimeImmutable())->modify("-{$days} days");

        $deleted = $this->repo->deleteExpiredAndRevoked($cutoff);
        $io->success("Cancellati $deleted refresh token più vecchi di $days giorni.");

        return Command::SUCCESS;
    }
}
