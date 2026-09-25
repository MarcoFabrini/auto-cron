<?php

declare(strict_types=1);

namespace App\Tests\Functional\MessageHandler;

use App\Enum\OrgRole;
use App\Enum\ReminderUrgency;
use App\Entity\PushSubscription;
use App\Enum\PushPlatform;
use App\Message\SendReminderNotificationMessage;
use App\MessageHandler\SendReminderNotificationHandler;
use App\Tests\Factory\OrganizationMemberFactory;
use App\Tests\Factory\RefuelingFactory;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\UserFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Mime\Email;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * Test del flow async dei reminder. Verifica che l'handler:
 * - skippi reminder completati (early return)
 * - chiami il PushDispatcher (in test = FakePushNotifier che logga)
 * - invii email Mailer (in test = MailerInterface con transport null o profiler)
 *
 * Nota: in env test il Messenger transport "async" è in-memory, quindi possiamo
 * istanziare l'handler direttamente e invocarlo come funzione.
 */
final class SendReminderNotificationHandlerTest extends KernelTestCase
{
    use ResetDatabase;
    use Factories;

    private SendReminderNotificationHandler $handler;
    private EntityManagerInterface $em;

    protected function setUp(): void
    {
        parent::setUp();
        self::bootKernel();
        $this->handler = static::getContainer()->get(SendReminderNotificationHandler::class);
        $this->em = static::getContainer()->get(EntityManagerInterface::class);
    }

    public function testHandlerSkipsCompletedReminder(): void
    {
        $reminder = ReminderFactory::createOne([
            'completedAt' => new \DateTimeImmutable(), // già completato
        ]);

        // L'handler non deve sollevare eccezioni né accedere a member/push/mail
        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        self::assertCount(0, static::getMailerMessages());
    }

    public function testHandlerSkipsMissingReminder(): void
    {
        ($this->handler)(new SendReminderNotificationMessage(999999));
        self::assertCount(0, static::getMailerMessages());
    }

    public function testHandlerNotifiesOnlyUsersWhoCanSeeTheVehicle(): void
    {
        $vehicle = VehicleFactory::createOne();
        $org = $vehicle->getOrganization();

        $owner = UserFactory::createOne(['email' => 'owner@test.it']);
        $sharedMember = UserFactory::createOne(['email' => 'shared@test.it']);
        $strangerMember = UserFactory::createOne(['email' => 'stranger@test.it']);
        $uPending = UserFactory::createOne(['email' => 'pending@test.it']);

        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $owner, 'role' => OrgRole::OWNER]);
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $sharedMember, 'role' => OrgRole::MEMBER]);
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $strangerMember, 'role' => OrgRole::MEMBER]);
        OrganizationMemberFactory::createOne([
            'organization' => $org, 'user' => $uPending,
            'role' => OrgRole::MEMBER,
            'acceptedAt' => null, // pending
        ]);
        // Il membro "shared" ha una condivisione del veicolo; "stranger" no.
        VehicleShareFactory::createOne(['vehicle' => $vehicle, 'user' => $sharedMember]);

        $reminder = ReminderFactory::createOne([
            'organization' => $org,
            'vehicle' => $vehicle,
            'description' => 'Revisione',
            'dueDate' => new \DateTimeImmutable('+15 days'),
        ]);

        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        // Filtra solo le mail generate dal nostro handler (subject "AutoCron — Scadenza: ...").
        // Necessario perché Symfony profiler collector può accumulare messaggi inter-test
        // se non resettato; restringere via subject + recipients noti rende l'assertion
        // robusta indipendentemente dall'isolamento del collector.
        $ourMessages = $this->filterHandlerEmails($reminder->getDescription());
        $recipients = $this->extractRecipients($ourMessages);
        sort($recipients);

        // Owner dell'org (vede tutto) + chi ha la condivisione. Non il membro senza accesso al
        // veicolo (non lo vedrebbe nell'app: la notifica rivelerebbe un veicolo altrui) né il pending.
        self::assertSame(
            ['owner@test.it', 'shared@test.it'],
            array_values(array_unique($recipients)),
        );
        self::assertEmailTextBodyContains($ourMessages[0], 'Revisione');
    }

    public function testHandlerCallsPushDispatcherForUsersWithSubscriptions(): void
    {
        $vehicle = VehicleFactory::createOne();
        $org = $vehicle->getOrganization();
        $user = UserFactory::createOne(['email' => 'pushed@test.it']);
        OrganizationMemberFactory::createOne(['organization' => $org, 'user' => $user, 'role' => OrgRole::OWNER]);

        // Crea una push subscription Web
        $sub = (new PushSubscription())
            ->setUser($user)
            ->setPlatform(PushPlatform::WEB)
            ->setEndpoint('https://push.example.com/endpoint')
            ->setP256dh('p256dh-key')
            ->setAuthSecret('auth-secret');
        $this->em->persist($sub);
        $this->em->flush();

        $reminder = ReminderFactory::createOne([
            'organization' => $org,
            'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+10 days'),
        ]);

        // In env test PushDispatcher usa FakePushNotifier (logga, ritorna ok)
        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        $ourMessages = $this->filterHandlerEmails($reminder->getDescription());
        $recipients = $this->extractRecipients($ourMessages);

        self::assertSame(['pushed@test.it'], array_values(array_unique($recipients)));
    }

    public function testHandlerSkipsReminderThatIsNotDueYet(): void
    {
        [$vehicle, $org] = $this->vehicleWithOwner('early@test.it');
        $reminder = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+100 days'), 'notifyDaysBefore' => 30,
            'description' => 'Ancora presto',
        ]);

        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        self::assertCount(0, $this->filterHandlerEmails('Ancora presto'));
        self::assertNull($reminder->getNotifiedUrgency());
    }

    public function testKmReminderIsNotifiedWhenTheOdometerGetsCloseAndMentionsKm(): void
    {
        [$vehicle, $org] = $this->vehicleWithOwner('km@test.it', initialKm: 99_500);
        $reminder = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => null, 'dueKm' => 100_000, 'description' => 'Cambio olio',
        ]);

        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        $mails = $this->filterHandlerEmails('Cambio olio');
        self::assertCount(1, $mails);
        self::assertEmailTextBodyContains($mails[0], '100.000 km');
        self::assertEmailTextBodyContains($mails[0], '99.500 km');
        self::assertSame(ReminderUrgency::SOON, $reminder->getNotifiedUrgency());
    }

    public function testKmReminderFarFromThresholdIsNotNotified(): void
    {
        [$vehicle, $org] = $this->vehicleWithOwner('kmfar@test.it', initialKm: 20_000);
        $reminder = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => null, 'dueKm' => 100_000, 'description' => 'Tagliando lontano',
        ]);

        ($this->handler)(new SendReminderNotificationMessage((int) $reminder->getId()));

        self::assertCount(0, $this->filterHandlerEmails('Tagliando lontano'));
    }

    public function testOneNotificationPerLevelThenEscalatesWhenKmArePassed(): void
    {
        [$vehicle, $org] = $this->vehicleWithOwner('esc@test.it', initialKm: 99_500);
        $reminder = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => null, 'dueKm' => 100_000, 'description' => 'Gomme',
        ]);
        $message = new SendReminderNotificationMessage((int) $reminder->getId());

        ($this->handler)($message);
        ($this->handler)($message); // doppione in coda / cron rieseguito: scartato
        self::assertCount(1, $this->filterHandlerEmails('Gomme'), 'stesso livello: una sola notifica');

        // L'utente registra un rifornimento oltre la soglia → "scaduto": nuova notifica, e basta.
        RefuelingFactory::createOne(['organization' => $org, 'vehicle' => $vehicle, 'km' => 100_400]);
        ($this->handler)($message);
        ($this->handler)($message);

        $mails = $this->filterHandlerEmails('Gomme');
        self::assertCount(2, $mails);
        self::assertEmailHtmlBodyContains($mails[1], 'Scadenza superata');
        self::assertSame(ReminderUrgency::OVERDUE, $reminder->getNotifiedUrgency());
    }

    /**
     * @return array{0: \App\Entity\Vehicle, 1: \App\Entity\Organization}
     */
    private function vehicleWithOwner(string $email, int $initialKm = 0): array
    {
        $vehicle = VehicleFactory::createOne(['initialKm' => $initialKm]);
        $org = $vehicle->getOrganization();
        OrganizationMemberFactory::createOne([
            'organization' => $org,
            'user' => UserFactory::createOne(['email' => $email]),
            'role' => OrgRole::OWNER,
        ]);

        return [$vehicle, $org];
    }

    /**
     * Filtra mail generate dal nostro handler via subject prefix "AutoCron — Scadenza: <description>".
     *
     * @return list<Email>
     */
    private function filterHandlerEmails(string $reminderDescription): array
    {
        $expectedSubject = 'AutoCron — Scadenza: '.$reminderDescription;
        $filtered = [];
        foreach (static::getMailerMessages() as $msg) {
            if ($msg instanceof Email && $msg->getSubject() === $expectedSubject) {
                $filtered[] = $msg;
            }
        }
        return $filtered;
    }

    /**
     * @param list<Email> $messages
     * @return list<string>
     */
    private function extractRecipients(array $messages): array
    {
        $recipients = [];
        foreach ($messages as $msg) {
            foreach ($msg->getTo() as $addr) {
                $recipients[] = $addr->getAddress();
            }
        }
        return $recipients;
    }
}
