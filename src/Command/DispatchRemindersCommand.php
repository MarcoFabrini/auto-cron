<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\Reminder;
use App\Message\SendReminderNotificationMessage;
use App\Repository\ReminderRepository;
use App\Service\VehicleStatsService;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Accoda `SendReminderNotificationMessage` per i promemoria che hanno raggiunto un nuovo
 * livello di urgenza: "in scadenza" (entro `notifyDaysBefore` giorni, o entro
 * {@see Reminder::KM_SOON_THRESHOLD} km dal chilometraggio attuale del veicolo) e poi "scaduto".
 *
 * Pensato per un cron giornaliero (`0 7 * * *  php bin/console app:reminders:dispatch`).
 *
 * Idempotenza: una notifica per livello, non una al giorno. Il livello notificato sta sul
 * promemoria (`notifiedUrgency`) ed è scritto dall'handler dopo l'invio; rieseguire il job
 * prima che l'handler giri accoda messaggi doppi, ma l'handler ricontrolla e scarta i doppioni.
 */
#[AsCommand(name: 'app:reminders:dispatch', description: 'Dispatch reminder notifications (date and km based)')]
final class DispatchRemindersCommand extends Command
{
    public function __construct(
        private readonly ReminderRepository $reminderRepo,
        private readonly VehicleStatsService $vehicleStats,
        private readonly MessageBusInterface $bus,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'List reminders without dispatching');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $today = new \DateTimeImmutable('today');

        /** @var array<int, int> $kmByVehicle km attuali per veicolo: un solo calcolo anche con più promemoria */
        $kmByVehicle = [];
        $due = [];

        foreach ($this->reminderRepo->findNotificationCandidates() as $r) {
            $currentKm = null;
            if ($r->getDueKm() !== null) {
                $vehicleId = (int) $r->getVehicle()->getId();
                $currentKm = $kmByVehicle[$vehicleId] ??= $this->vehicleStats->currentKm($r->getVehicle());
            }

            $urgency = $r->urgency($currentKm, $today);
            if ($r->needsNotification($urgency)) {
                $due[] = [$r, $urgency];
            }
        }

        if ($due === []) {
            $io->info('Nessun promemoria da notificare.');
            return Command::SUCCESS;
        }

        $io->section(sprintf('%d promemoria da notificare', count($due)));

        foreach ($due as [$r, $urgency]) {
            $io->writeln(sprintf(
                '  [%d] %s — %s (%s, scadenza: %s)',
                $r->getId(),
                $r->getVehicle()->getName(),
                $r->getDescription(),
                $urgency->value,
                implode(' / ', array_filter([
                    $r->getDueDate()?->format('Y-m-d'),
                    $r->getDueKm() !== null ? $r->getDueKm().' km' : null,
                ])),
            ));

            if (!$dryRun) {
                $this->bus->dispatch(new SendReminderNotificationMessage((int) $r->getId()));
            }
        }

        if ($dryRun) {
            $io->warning('Dry-run: nessun messaggio accodato.');
        } else {
            $io->success(sprintf('%d messaggi accodati su transport async', count($due)));
        }

        return Command::SUCCESS;
    }
}
