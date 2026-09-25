<?php

declare(strict_types=1);

namespace App\Service\Gdpr;

use App\Entity\Attachment;
use App\Entity\Expense;
use App\Entity\Maintenance;
use App\Entity\Organization;
use App\Entity\PushSubscription;
use App\Entity\Refueling;
use App\Entity\Reminder;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\AttachmentEntityType;
use App\Enum\OrgRole;
use App\Repository\AttachmentRepository;
use App\Repository\ExpenseRepository;
use App\Repository\MaintenanceRepository;
use App\Repository\PushSubscriptionRepository;
use App\Repository\RefuelingRepository;
use App\Repository\ReminderRepository;
use App\Repository\VehicleRepository;
use App\Repository\VehicleShareRepository;

/**
 * GDPR Art. 20 (data portability) — costruisce snapshot JSON dell'utente (#5.4).
 *
 * Include:
 * - profilo (email, name, locale, timestamps)
 * - memberships (org_id, role, joined_at)
 * - push_subscriptions (endpoint metadata, mai chiavi)
 * - vehicle_shares (ricevuti)
 * - per org dove user è owner: vehicles + maintenance + refueling + expense + reminder + attachments (metadata)
 *
 * Esclude:
 * - password hash (sensibile, no portabilità)
 * - refresh_tokens (auth-only, non personal data)
 * - allegati binari (solo metadata; copia file separata se serve)
 * - p256dh / authSecret push (chiavi crypto)
 */
final class GdprExportService
{
    public function __construct(
        private readonly VehicleRepository $vehicleRepo,
        private readonly VehicleShareRepository $shareRepo,
        private readonly MaintenanceRepository $maintenanceRepo,
        private readonly RefuelingRepository $refuelingRepo,
        private readonly ExpenseRepository $expenseRepo,
        private readonly ReminderRepository $reminderRepo,
        private readonly AttachmentRepository $attachmentRepo,
        private readonly PushSubscriptionRepository $pushRepo,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function export(User $user): array
    {
        return [
            'export_meta' => [
                'generated_at' => (new \DateTimeImmutable())->format(\DATE_ATOM),
                'gdpr_article' => 'Art. 20 — Right to data portability',
                'format' => 'json',
                'version' => 1,
            ],
            'user' => $this->serializeUser($user),
            'memberships' => $this->serializeMemberships($user),
            'push_subscriptions' => $this->serializePushSubscriptions($user),
            'vehicle_shares_received' => $this->serializeSharesReceived($user),
            'organizations_owned' => $this->serializeOwnedOrganizations($user),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeUser(User $user): array
    {
        return [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'first_name' => $user->getFirstName(),
            'last_name' => $user->getLastName(),
            'locale' => $user->getLocale(),
            'created_at' => $user->getCreatedAt()->format(\DATE_ATOM),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeMemberships(User $user): array
    {
        $out = [];
        foreach ($user->getMemberships() as $m) {
            $org = $m->getOrganization();
            $out[] = [
                'organization_id' => $org->getId(),
                'organization_slug' => $org->getSlug(),
                'organization_name' => $org->getName(),
                'role' => $m->getRole()->value,
                'joined_at' => $m->getCreatedAt()->format(\DATE_ATOM),
                'accepted_at' => $m->getAcceptedAt()?->format(\DATE_ATOM),
            ];
        }
        return $out;
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializePushSubscriptions(User $user): array
    {
        $out = [];
        foreach ($this->pushRepo->findBy(['user' => $user]) as $sub) {
            $out[] = $this->serializePushSubscription($sub);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializePushSubscription(PushSubscription $sub): array
    {
        $endpoint = $sub->getEndpoint() ?? '';

        return [
            'platform' => $sub->getPlatform()->value,
            'endpoint_prefix' => $endpoint !== '' ? substr($endpoint, 0, 32).'…' : null,
            'device_label' => $sub->getDeviceLabel(),
            'user_agent' => $sub->getUserAgent(),
            'created_at' => $sub->getCreatedAt()->format(\DATE_ATOM),
            'last_seen_at' => $sub->getLastSeenAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeSharesReceived(User $user): array
    {
        $out = [];
        foreach ($this->shareRepo->findBy(['user' => $user]) as $share) {
            $out[] = $this->serializeVehicleShare($share);
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVehicleShare(VehicleShare $share): array
    {
        $vehicle = $share->getVehicle();
        return [
            'vehicle_id' => $vehicle->getId(),
            'vehicle_name' => $vehicle->getName(),
            'role' => $share->getRole()->value,
            'invited_at' => $share->getCreatedAt()->format(\DATE_ATOM),
            'accepted_at' => $share->getAcceptedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function serializeOwnedOrganizations(User $user): array
    {
        $out = [];
        foreach ($user->getMemberships() as $m) {
            if ($m->getRole() !== OrgRole::OWNER) {
                continue;
            }
            $out[] = $this->serializeOrganization($m->getOrganization());
        }
        return $out;
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeOrganization(Organization $org): array
    {
        $vehicles = $this->vehicleRepo->findBy(['organization' => $org]);

        return [
            'id' => $org->getId(),
            'slug' => $org->getSlug(),
            'name' => $org->getName(),
            'created_at' => $org->getCreatedAt()->format(\DATE_ATOM),
            'vehicles' => array_map(fn ($v) => $this->serializeVehicle($v), $vehicles),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeVehicle(Vehicle $v): array
    {
        return [
            'id' => $v->getId(),
            'name' => $v->getName(),
            'brand' => $v->getBrand(),
            'model' => $v->getModel(),
            'year' => $v->getYear(),
            'license_plate' => $v->getLicensePlate(),
            'vin' => $v->getVin(),
            'fuel_type' => $v->getFuelType()->value,
            'secondary_fuel_type' => $v->getSecondaryFuelType()?->value,
            'initial_km' => $v->getInitialKm(),
            'notes' => $v->getNotes(),
            'archived_at' => $v->getArchivedAt()?->format(\DATE_ATOM),
            'created_at' => $v->getCreatedAt()->format(\DATE_ATOM),
            'maintenance' => array_map(fn ($x) => $this->serializeMaintenance($x), $this->maintenanceRepo->findBy(['vehicle' => $v])),
            'refueling' => array_map(fn ($x) => $this->serializeRefueling($x), $this->refuelingRepo->findBy(['vehicle' => $v])),
            'expenses' => array_map(fn ($x) => $this->serializeExpense($x), $this->expenseRepo->findBy(['vehicle' => $v])),
            'reminders' => array_map(fn ($x) => $this->serializeReminder($x), $this->reminderRepo->findBy(['vehicle' => $v])),
            'attachments' => array_map(
                fn ($x) => $this->serializeAttachment($x),
                $this->attachmentRepo->findByEntity(AttachmentEntityType::VEHICLE, (string) $v->getId(), $v->getOrganization()),
            ),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeMaintenance(Maintenance $m): array
    {
        return [
            'id' => $m->getId(),
            'type' => $m->getType()->value,
            'category' => $m->getCategory()->value,
            'performed_at' => $m->getPerformedAt()->format('Y-m-d'),
            'km' => $m->getKm(),
            'cost' => $m->getCost(),
            'workshop' => $m->getWorkshop(),
            'description' => $m->getDescription(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeRefueling(Refueling $r): array
    {
        return [
            'id' => $r->getId(),
            'refueled_at' => $r->getRefueledAt()->format('Y-m-d'),
            'km' => $r->getKm(),
            'liters' => $r->getLiters(),
            'price_per_liter' => $r->getPricePerLiter(),
            'total_cost' => $r->getTotalCost(),
            'fuel_type' => $r->getFuelType()->value,
            'full_tank' => $r->isFullTank(),
            'station' => $r->getStation(),
            'notes' => $r->getNotes(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeExpense(Expense $e): array
    {
        return [
            'id' => $e->getId(),
            'category' => $e->getCategory()->value,
            'occurred_at' => $e->getOccurredAt()->format('Y-m-d'),
            'amount' => $e->getAmount(),
            'description' => $e->getDescription(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeReminder(Reminder $r): array
    {
        return [
            'id' => $r->getId(),
            'type' => $r->getType()->value,
            'description' => $r->getDescription(),
            'due_date' => $r->getDueDate()?->format('Y-m-d'),
            'due_km' => $r->getDueKm(),
            'notify_days_before' => $r->getNotifyDaysBefore(),
            'completed_at' => $r->getCompletedAt()?->format(\DATE_ATOM),
            'last_notified_at' => $r->getLastNotifiedAt()?->format(\DATE_ATOM),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAttachment(Attachment $a): array
    {
        return [
            'id' => $a->getId(),
            'entity_type' => $a->getEntityType()->value,
            'entity_id' => $a->getEntityId(),
            'original_filename' => $a->getOriginalFilename(),
            'mime_type' => $a->getMimeType(),
            'size_bytes' => $a->getSizeBytes(),
            'stored_path' => $a->getStoredPath(),
            'created_at' => $a->getCreatedAt()->format(\DATE_ATOM),
        ];
    }
}
