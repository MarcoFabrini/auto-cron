<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\Organization;
use App\Entity\User;
use App\Entity\Vehicle;
use App\Enum\ShareRole;
use App\Repository\OrganizationMemberRepository;
use App\Repository\VehicleShareRepository;

/**
 * Logica centrale di accesso ai veicoli (riusata da tutti i Voter di risorse
 * scoped per veicolo: VehicleVoter, MaintenanceVoter, RefuelingVoter, ecc).
 *
 * Politica di accesso:
 * - Proprietario del veicolo (share `admin`, chi l'ha creato) → accesso totale.
 * - Owner/Admin dell'Organization → accesso totale a tutti i veicoli dell'org (gestione).
 * - Condivisione (share `viewer`, o il vecchio `editor`) → SOLA LETTURA: vede il veicolo e
 *   i suoi dati, non modifica nulla.
 *
 * Accesso ≠ proprietà: totali e grafici della dashboard, scadenze in arrivo e notifiche
 * riguardano solo i veicoli di cui l'utente è proprietario (share `admin` accettato), anche
 * per owner/admin dell'org, che vedono gli altri veicoli ma non li hanno "propri".
 */
final class VehicleAccessChecker
{
    public function __construct(
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly VehicleShareRepository $shareRepo,
    ) {
    }

    public function canView(User $user, Vehicle $vehicle): bool
    {
        return $this->level($user, $vehicle) !== null;
    }

    public function canEdit(User $user, Vehicle $vehicle): bool
    {
        return self::permissionsForLevel($this->level($user, $vehicle))['canEdit'];
    }

    public function canDelete(User $user, Vehicle $vehicle): bool
    {
        return self::permissionsForLevel($this->level($user, $vehicle))['canDelete'];
    }

    /**
     * Gestire chi vede il veicolo (condivisioni) spetta solo a chi ne è proprietario/admin:
     * chi ha una condivisione non può allargare né togliere accessi.
     */
    public function canShare(User $user, Vehicle $vehicle): bool
    {
        return self::permissionsForLevel($this->level($user, $vehicle))['canShare'];
    }

    /**
     * Ritorna il "livello" di accesso dell'utente al veicolo, o null se nessuno.
     * Valori: 'org_admin' | 'share_admin' | 'share_viewer'.
     */
    public function level(User $user, Vehicle $vehicle): ?string
    {
        $membership = $this->memberRepo->findMembership($user, $vehicle->getOrganization());
        if (!$membership || !$membership->isAccepted()) {
            return null;
        }

        if ($membership->getRole()->isOrgAdmin()) {
            return self::resolveLevel(true, null); // lo share non cambia nulla: niente seconda query
        }

        $share = $this->shareRepo->findForUserAndVehicle($user, $vehicle);

        return self::resolveLevel(false, $share !== null && $share->isAccepted() ? $share->getRole() : null);
    }

    /**
     * Livello da dati già caricati (membership accettata + ruolo dello share accettato), per le
     * liste: evita due query per veicolo. Stessa regola di {@see self::level()}.
     */
    public static function resolveLevel(bool $isOrgAdmin, ?ShareRole $acceptedShareRole): ?string
    {
        if ($isOrgAdmin) {
            return 'org_admin';
        }

        return match ($acceptedShareRole) {
            null => null,
            ShareRole::ADMIN => 'share_admin',
            // Qualsiasi condivisione che non sia la proprietà è in sola lettura.
            ShareRole::EDITOR, ShareRole::VIEWER => 'share_viewer',
        };
    }

    /** @return array{canEdit: bool, canDelete: bool, canShare: bool} */
    public static function permissionsForLevel(?string $level): array
    {
        $full = $level === 'org_admin' || $level === 'share_admin';

        return ['canEdit' => $full, 'canDelete' => $full, 'canShare' => $full];
    }

    public function isOrgAdmin(User $user, Organization $org): bool
    {
        $membership = $this->memberRepo->findMembership($user, $org);
        if (!$membership || !$membership->isAccepted()) {
            return false;
        }
        return $membership->getRole()->isOrgAdmin();
    }
}
