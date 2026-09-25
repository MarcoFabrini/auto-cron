<?php

declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\Reminder;
use App\Entity\User;
use App\Enum\ReminderUrgency;
use App\Message\SendReminderNotificationMessage;
use App\Repository\OrganizationMemberRepository;
use App\Repository\ReminderRepository;
use App\Service\AppMailer;
use App\Service\MailBuilder;
use App\Service\Push\PushDispatcher;
use App\Service\Push\PushPayload;
use App\Service\VehicleAccessChecker;
use App\Service\VehicleStatsService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

/**
 * Handler delle notifiche di scadenza.
 *
 * Ricontrolla l'urgenza al momento dell'invio (il promemoria può essere stato completato,
 * modificato o già notificato da un doppione in coda) e manda push (a tutti i device) + email
 * agli utenti che possono VEDERE il veicolo: owner/admin dell'org e chi ha una condivisione
 * accettata. Un membro senza accesso al veicolo non riceve nulla.
 */
#[AsMessageHandler]
final class SendReminderNotificationHandler
{
    public function __construct(
        private readonly ReminderRepository $reminderRepo,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly VehicleAccessChecker $access,
        private readonly VehicleStatsService $vehicleStats,
        private readonly PushDispatcher $pushDispatcher,
        private readonly AppMailer $mailer,
        private readonly MailBuilder $mailBuilder,
        private readonly EntityManagerInterface $em,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function __invoke(SendReminderNotificationMessage $message): void
    {
        $reminder = $this->reminderRepo->find($message->reminderId);
        if (!$reminder) {
            $this->logger->warning('SendReminderNotification: reminder gone', ['id' => $message->reminderId]);
            return;
        }
        if ($reminder->isCompleted()) {
            return; // l'utente l'ha completato nel frattempo
        }

        $vehicle = $reminder->getVehicle();
        $currentKm = $reminder->getDueKm() !== null ? $this->vehicleStats->currentKm($vehicle) : null;
        $urgency = $reminder->urgency($currentKm, new \DateTimeImmutable('today'));
        if (!$reminder->needsNotification($urgency)) {
            return; // niente in scadenza, o questo livello è già stato notificato
        }

        $payload = $this->buildPushPayload($reminder, $urgency, $currentKm);

        foreach ($this->memberRepo->findAcceptedByOrganization($reminder->getOrganization()) as $member) {
            $user = $member->getUser();
            if (!$this->access->canView($user, $vehicle)) {
                continue;
            }
            $this->sendPush($user, $payload);
            $this->sendEmail($user, $reminder, $urgency, $currentKm);
        }

        // Una notifica per livello: il prossimo invio solo se l'urgenza sale (o cambiano le scadenze).
        $reminder->markNotified($urgency);
        $this->em->flush();
    }

    private function buildPushPayload(Reminder $r, ReminderUrgency $urgency, ?int $currentKm): PushPayload
    {
        $deadline = [];
        if ($r->getDueDate() !== null) {
            $deadline[] = 'entro il '.$r->getDueDate()->format('d/m/Y');
        }
        if ($r->getDueKm() !== null) {
            $deadline[] = 'entro '.self::km($r->getDueKm()).($currentKm !== null ? ' (ora '.self::km($currentKm).')' : '');
        }

        return new PushPayload(
            title: sprintf($urgency === ReminderUrgency::OVERDUE ? 'Scaduto %s' : 'Scadenza %s', $r->getType()->value),
            body: sprintf('%s — %s (%s)', $r->getVehicle()->getName(), $r->getDescription(), implode(', ', $deadline)),
            data: [
                'reminderId' => (string) $r->getId(),
                'vehicleId' => (string) $r->getVehicle()->getId(),
                'type' => 'reminder_due',
            ],
            url: '/reminders/'.$r->getId(),
        );
    }

    private static function km(int $km): string
    {
        return number_format($km, 0, ',', '.').' km';
    }

    private function sendPush(User $user, PushPayload $payload): void
    {
        try {
            $this->pushDispatcher->notifyUser($user, $payload);
        } catch (\Throwable $e) {
            $this->logger->error('Reminder push failed', [
                'user_id' => $user->getId(),
                'error' => $e->getMessage(),
            ]);
        }
    }

    private function sendEmail(User $user, Reminder $r, ReminderUrgency $urgency, ?int $currentKm): void
    {
        try {
            $email = $this->mailBuilder->reminder($user, $r, $urgency, $currentKm)->to($user->getEmail());
            $this->mailer->send($email);
        } catch (\Throwable $e) {
            $this->logger->error('Reminder email failed', [
                'user_id' => $user->getId(),
                'email' => $user->getEmail(),
                'error' => $e->getMessage(),
            ]);
        }
    }

}
