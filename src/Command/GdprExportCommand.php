<?php

declare(strict_types=1);

namespace App\Command;

use App\Repository\UserRepository;
use App\Service\Gdpr\GdprExportService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * GDPR Art. 20 — data portability (#5.4).
 *
 * Esporta tutti i dati dell'utente in JSON. Usabile via support ticket o
 * self-service futuro endpoint API.
 *
 *   php bin/console app:gdpr:export user@example.com --output=/var/backups/gdpr/user.json
 *   php bin/console app:gdpr:export user@example.com  # stdout
 */
#[AsCommand(name: 'app:gdpr:export', description: 'Export all user data (GDPR Art. 20) as JSON')]
final class GdprExportCommand extends Command
{
    public function __construct(
        private readonly UserRepository $userRepo,
        private readonly GdprExportService $exporter,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'User email')
            ->addOption('output', 'o', InputOption::VALUE_REQUIRED, 'Output file path (default: stdout)')
            ->addOption('pretty', null, InputOption::VALUE_NONE, 'Pretty-print JSON');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = (string) $input->getArgument('email');

        $user = $this->userRepo->findOneByEmail($email);
        if ($user === null) {
            $io->error(sprintf('User "%s" not found', $email));
            return Command::FAILURE;
        }

        $data = $this->exporter->export($user);
        $flags = $input->getOption('pretty') || $input->getOption('output')
            ? \JSON_PRETTY_PRINT | \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES
            : \JSON_UNESCAPED_UNICODE | \JSON_UNESCAPED_SLASHES;
        $json = json_encode($data, $flags | \JSON_THROW_ON_ERROR);

        $outputPath = $input->getOption('output');
        if ($outputPath !== null) {
            $dir = dirname((string) $outputPath);
            if (!is_dir($dir) && !mkdir($dir, 0o755, true) && !is_dir($dir)) {
                $io->error(sprintf('Cannot create directory "%s"', $dir));
                return Command::FAILURE;
            }
            file_put_contents($outputPath, $json);
            $bytes = strlen($json);
            $io->success(sprintf('GDPR export for "%s" written to %s (%d bytes)', $email, $outputPath, $bytes));
        } else {
            $output->writeln($json);
        }

        return Command::SUCCESS;
    }
}
