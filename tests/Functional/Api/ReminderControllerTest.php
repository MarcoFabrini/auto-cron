<?php

declare(strict_types=1);

namespace App\Tests\Functional\Api;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\OrgRole;
use App\Tests\Factory\ReminderFactory;
use App\Tests\Factory\VehicleFactory;
use App\Tests\Factory\VehicleShareFactory;
use App\Tests\Support\ApiTestCase;
use Doctrine\DBAL\Connection;

final class ReminderControllerTest extends ApiTestCase
{
    public function testCreateReminder(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $vehicle->getId(),
            'type' => 'inspection',
            'description' => 'Revisione biennale',
            'dueDate' => '2026-12-31',
            'notifyDaysBefore' => 30,
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(201);
        self::assertSame('Revisione biennale', $this->jsonBody()['description']);
        self::assertNull($this->jsonBody()['completedAt']);
    }

    public function testCreateReminderWithAnUnknownTypeIsReportedWithAnI18nKey(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $vehicle->getId(), 'type' => 'not-a-type', 'description' => 'x', 'dueDate' => '2026-12-31',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame([['field' => 'type', 'message' => 'common.invalid_value']], $this->jsonBody()['errors']);
    }

    public function testCreateReminderWithoutAnyDueIsRejected(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $vehicle->getId(),
            'type' => 'custom',
            'description' => 'Senza scadenza',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
    }

    public function testCompleteEndpointMarksAsCompleted(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);

        $this->jsonRequest('POST', '/api/reminders/'.$r->getId().'/complete', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertNotNull($this->jsonBody()['completedAt']);
    }

    public function testListExcludesCompletedByDefault(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        ReminderFactory::createOne(['organization' => $org, 'vehicle' => $vehicle]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'completedAt' => new \DateTimeImmutable(),
        ]);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$vehicle->getId(), accessToken: $token);
        self::assertCount(1, $this->jsonBody());

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$vehicle->getId().'&onlyActive=false', accessToken: $token);
        self::assertCount(2, $this->jsonBody());
    }

    /** La finestra di preavviso serve al client per calcolare "in scadenza": deve stare anche nelle liste. */
    public function testListAndUpcomingExposeNotifyDaysBefore(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = $this->ownedVehicle($user, $org);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'), 'notifyDaysBefore' => 12,
        ]);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$vehicle->getId(), accessToken: $token);
        self::assertResponseIsSuccessful();
        /** @var list<array{notifyDaysBefore: int}> $list */
        $list = $this->jsonBody();
        self::assertSame(12, $list[0]['notifyDaysBefore']);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $token);
        self::assertResponseIsSuccessful();
        /** @var list<array{notifyDaysBefore: int}> $upcoming */
        $upcoming = $this->jsonBody();
        self::assertSame(12, $upcoming[0]['notifyDaysBefore']);
    }

    public function testUpcomingAggregatesAcrossTheOwnedVehicles(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicleA = $this->ownedVehicle($user, $org);
        $vehicleB = $this->ownedVehicle($user, $org);

        $overdue = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleA,
            'dueDate' => new \DateTimeImmutable('-2 days'),
        ]);
        $soon = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleB,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicleA,
            'dueDate' => new \DateTimeImmutable('+90 days'), // fuori dalla finestra default (30gg)
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $token);

        self::assertResponseIsSuccessful();
        /** @var list<array{id: int, vehicle: array{id: int}}> $body */
        $body = $this->jsonBody();
        self::assertSame([$overdue->getId(), $soon->getId()], array_column($body, 'id'));
        self::assertSame($vehicleA->getId(), $body[0]['vehicle']['id']);
    }

    public function testUpcomingIsolatesOtherOrganizations(): void
    {
        [, $orgA, $tokenA] = $this->createAuthenticatedUser();
        [, $orgB] = $this->createAuthenticatedUser();
        $vehicleB = VehicleFactory::createOne(['organization' => $orgB]);
        ReminderFactory::createOne([
            'organization' => $orgB, 'vehicle' => $vehicleB,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $tokenA);

        self::assertResponseIsSuccessful();
        self::assertSame([], $this->jsonBody());
    }

    public function testUpcomingOnlyCoversOwnedVehiclesEvenForTheOrgOwner(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser(); // owner dell'org: vede tutti i veicoli
        $mine = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $this->ownedVehicle($user, $org),
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        // Veicolo di un altro membro: l'owner dell'org lo vede, ma non è suo.
        $othersVehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::new()->asAdmin()->create(['vehicle' => $othersVehicle]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $othersVehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);
        // Veicolo condiviso con lui in sola lettura: nemmeno questo.
        $sharedVehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::createOne(['vehicle' => $sharedVehicle, 'user' => $user]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $sharedVehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame([$mine->getId()], array_column($this->jsonBody(), 'id'));
    }

    public function testUpcomingRespectsDaysAndLimitParams(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = $this->ownedVehicle($user, $org);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+50 days'),
        ]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('+5 days'),
        ]);

        $this->jsonRequest('GET', '/api/reminders/upcoming?days=90&limit=1', accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertCount(1, $this->jsonBody());
    }

    public function testUpdateChangesTheFieldsAndRearmsTheNotificationOnlyWhenTheDeadlineMoves(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle,
            'dueDate' => new \DateTimeImmutable('2026-12-31'), 'dueKm' => null, 'notifyDaysBefore' => 30,
            'description' => 'Tagliando',
        ]);
        $id = $r->getId();
        $connection = static::getContainer()->get(Connection::class);
        $connection->executeStatement("UPDATE reminders SET notified_urgency = 'soon' WHERE id = ?", [$id]);

        // Stessa scadenza, solo la descrizione cambia: il livello già notificato resta (niente doppia notifica)
        $this->jsonRequest('PUT', '/api/reminders/'.$id, [
            'type' => 'service', 'description' => 'Tagliando completo', 'dueDate' => '2026-12-31', 'notifyDaysBefore' => 30,
        ], accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertSame('Tagliando completo', $this->jsonBody()['description']);
        self::assertSame('soon', $connection->fetchOne('SELECT notified_urgency FROM reminders WHERE id = ?', [$id]));

        // La scadenza si sposta: la notifica si riarma
        $this->jsonRequest('PUT', '/api/reminders/'.$id, [
            'type' => 'service', 'description' => 'Tagliando completo', 'dueDate' => '2027-03-01', 'dueKm' => 120000, 'notifyDaysBefore' => 14,
        ], accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertSame(120000, $this->jsonBody()['dueKm']);
        self::assertNull($connection->fetchOne('SELECT notified_urgency FROM reminders WHERE id = ?', [$id]) ?: null);
        self::assertSame('2027-03-01', $connection->fetchOne('SELECT DATE(due_date) FROM reminders WHERE id = ?', [$id]));
    }

    public function testUpdateDoesNotNeedAVehicleId(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle, 'dueDate' => new \DateTimeImmutable('2026-12-31'), 'description' => 'Originale',
        ]);

        $this->jsonRequest('PUT', '/api/reminders/'.$r->getId(), [
            'type' => 'custom', 'description' => 'Senza veicolo', 'dueDate' => '2026-12-31',
        ], accessToken: $token);

        self::assertResponseIsSuccessful();
        self::assertSame('Senza veicolo', $this->jsonBody()['description']);
        self::assertSame($vehicle->getId(), $this->jsonBody()['vehicle']['id']);
    }

    public function testUpdateIgnoresAVehicleIdInTheBodyAndNeverMovesTheReminder(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $other = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle, 'dueDate' => new \DateTimeImmutable('2026-12-31'), 'description' => 'Originale',
        ]);
        $id = $r->getId();

        // Il client storico manda ancora vehicleId: non deve far fallire la PUT né spostare il promemoria
        foreach ([$vehicle->getId(), $other->getId(), 0] as $vehicleId) {
            $this->jsonRequest('PUT', '/api/reminders/'.$id, [
                'vehicleId' => $vehicleId, 'type' => 'custom', 'description' => 'Aggiornato', 'dueDate' => '2026-12-31',
            ], accessToken: $token);

            self::assertResponseIsSuccessful();
            self::assertSame($vehicle->getId(), $this->jsonBody()['vehicle']['id']);
        }
        self::assertSame(
            $vehicle->getId(),
            (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT vehicle_id FROM reminders WHERE id = ?', [$id]),
        );
    }

    public function testUpdateWithoutAnyDueIsRejectedAndLeavesTheReminderUntouched(): void
    {
        [, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        $r = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle, 'dueDate' => new \DateTimeImmutable('2026-12-31'), 'description' => 'Originale',
        ]);

        $this->jsonRequest('PUT', '/api/reminders/'.$r->getId(), [
            'vehicleId' => $vehicle->getId(), 'type' => 'custom', 'description' => 'Senza scadenza',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(422);
        self::assertSame('validation_failed', $this->jsonBody()['title']);
        self::assertSame([['field' => 'dueDate', 'message' => 'reminder.due_required']], $this->jsonBody()['errors']);

        $this->jsonRequest('GET', '/api/reminders/'.$r->getId(), accessToken: $token);
        self::assertSame('Originale', $this->jsonBody()['description']);
    }

    public function testListRequiresAVehicleIdAndHidesVehiclesOfOtherTenants(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        [, $otherOrg] = $this->createAuthenticatedUser();
        $foreign = VehicleFactory::createOne(['organization' => $otherOrg]);

        $this->jsonRequest('GET', '/api/reminders', accessToken: $token);
        self::assertResponseStatusCodeSame(400);
        self::assertSame('query.vehicle_id_required', $this->jsonBody()['title']);

        $this->jsonRequest('GET', '/api/reminders?vehicleId=-3', accessToken: $token);
        self::assertResponseStatusCodeSame(400);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$foreign->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(404);
        self::assertSame('vehicle.not_found', $this->jsonBody()['title']);
    }

    public function testCreateOnAVehicleOfAnotherOrganizationReturns404AndCreatesNothing(): void
    {
        [, , $token] = $this->createAuthenticatedUser();
        [, $otherOrg] = $this->createAuthenticatedUser();
        $foreign = VehicleFactory::createOne(['organization' => $otherOrg]);

        $this->jsonRequest('POST', '/api/reminders', [
            'vehicleId' => $foreign->getId(), 'type' => 'inspection', 'description' => 'Intruso', 'dueDate' => '2026-12-31',
        ], accessToken: $token);

        self::assertResponseStatusCodeSame(404);
        self::assertSame('vehicle.not_found', $this->jsonBody()['title']);
        self::assertSame(0, (int) static::getContainer()->get(Connection::class)->fetchOne('SELECT COUNT(*) FROM reminders'));
    }

    public function testReadingRemindersNeedsAccessToTheVehicle(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser(OrgRole::MEMBER);
        $hidden = VehicleFactory::createOne(['organization' => $org]);
        $shared = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::createOne(['vehicle' => $shared, 'user' => $user]); // viewer
        $hiddenReminder = ReminderFactory::createOne(['organization' => $org, 'vehicle' => $hidden]);
        $sharedReminder = ReminderFactory::createOne(['organization' => $org, 'vehicle' => $shared]);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$hidden->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(403);
        $this->jsonRequest('GET', '/api/reminders/'.$hiddenReminder->getId(), accessToken: $token);
        self::assertResponseStatusCodeSame(403);

        $this->jsonRequest('GET', '/api/reminders?vehicleId='.$shared->getId(), accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertSame([$sharedReminder->getId()], array_column($this->jsonBody(), 'id'));
    }

    public function testUpcomingClampsDaysAndLimit(): void
    {
        [$user, $org, $token] = $this->createAuthenticatedUser();
        $vehicle = $this->ownedVehicle($user, $org);
        $inAYear = ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle, 'dueDate' => new \DateTimeImmutable('+364 days'),
        ]);
        ReminderFactory::createOne([
            'organization' => $org, 'vehicle' => $vehicle, 'dueDate' => new \DateTimeImmutable('+400 days'),
        ]);

        // days enorme: il tetto è 365, i promemoria oltre l'anno non escono
        $this->jsonRequest('GET', '/api/reminders/upcoming?days=100000&limit=50', accessToken: $token);
        self::assertSame([$inAYear->getId()], array_column($this->jsonBody(), 'id'));

        // days/limit a 0 o negativi si portano al minimo (1), non producono errori né liste illimitate
        $this->jsonRequest('GET', '/api/reminders/upcoming?days=0&limit=-5', accessToken: $token);
        self::assertResponseIsSuccessful();
        self::assertSame([], $this->jsonBody());
    }

    private function ownedVehicle(User $owner, Organization $org): Vehicle
    {
        $vehicle = VehicleFactory::createOne(['organization' => $org]);
        VehicleShareFactory::new()->asAdmin()->create(['vehicle' => $vehicle, 'user' => $owner]);

        return $vehicle;
    }
}
