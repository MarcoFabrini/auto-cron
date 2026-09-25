<?php

declare(strict_types=1);

namespace App\Service;

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
 * - Owner/Admin dell'Organization → accesso totale a tutti i veicoli dell'org.
 * - Member dell'Organization → accesso solo ai veicoli per cui ha un VehicleShare accepted.
 *   Il livello di permesso (view/edit/delete) dipende dal ruolo dello share.
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
        return match ($this->level($user, $vehicle)) {
            'org_admin', 'share_admin', 'share_editor' => true,
            default => false,
        };
    }

    public function canDelete(User $user, Vehicle $vehicle): bool
    {
        return match ($this->level($user, $vehicle)) {
            'org_admin', 'share_admin' => true,
            default => false,
        };
    }

    /**
     * Ritorna il "livello" di accesso dell'utente al veicolo, o null se nessuno.
     * Valori: 'org_admin' | 'share_admin' | 'share_editor' | 'share_viewer'.
     */
    public function level(User $user, Vehicle $vehicle): ?string
    {
        $membership = $this->memberRepo->findMembership($user, $vehicle->getOrganization());
        if (!$membership || !$membership->isAccepted()) {
            return null;
        }

        if ($membership->getRole()->isOrgAdmin()) {
            return 'org_admin';
        }

        $share = $this->shareRepo->findForUserAndVehicle($user, $vehicle);
        if (!$share || !$share->isAccepted()) {
            return null;
        }

        return match ($share->getRole()) {
            ShareRole::ADMIN => 'share_admin',
            ShareRole::EDITOR => 'share_editor',
            ShareRole::VIEWER => 'share_viewer',
        };
    }

    public function isOrgAdmin(User $user, \App\Entity\Organization $org): bool
    {
        $membership = $this->memberRepo->findMembership($user, $org);
        if (!$membership || !$membership->isAccepted()) {
            return false;
        }
        return $membership->getRole()->isOrgAdmin();
    }
}
