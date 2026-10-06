<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\ShareRole;
use App\Repository\OrganizationMemberRepository;
use App\Repository\ReminderRepository;
use App\Repository\VehicleShareRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityNotFoundException;

/**
 * Trasferimento diretto della proprietà di un veicolo a un altro membro della stessa organizzazione.
 *
 * Si sposta solo la proprietà (lo share `admin` accettato, vedi {@see VehicleOwnership}): manutenzioni,
 * rifornimenti, spese, promemoria e allegati restano del veicolo. Dashboard, grafici, scadenze in arrivo
 * e notifiche seguono il proprietario ATTUALE, quindi passano al nuovo con tutto lo storico.
 *
 * Una sola transazione con lock pessimistico sulla riga del veicolo; dopo il lock si rilegge lo stato
 * (l'autorizzazione controllata dal voter può essere scaduta nell'attesa) e a fine commit esiste
 * esattamente UNO share `admin` accettato. Chi autorizza è `VehicleVoter::SHARE` nel controller; qui
 * si ricontrolla con la stessa regola ({@see VehicleAccessChecker}) su dati freschi.
 */
final class VehicleOwnershipTransfer
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleShareRepository $shareRepo,
        private readonly OrganizationMemberRepository $memberRepo,
        private readonly ReminderRepository $reminderRepo,
        private readonly VehicleOwnership $ownership,
    ) {
    }

    /**
     * @param bool $keepAccess true: il precedente proprietario resta con uno share `viewer`; false: lo share cade
     *
     * @throws VehicleTransferException
     */
    public function transfer(Vehicle $vehicle, int $recipientUserId, bool $keepAccess, User $actor): VehicleTransferOutcome
    {
        $org = $vehicle->getOrganization();

        return $this->em->wrapInTransaction(function () use ($vehicle, $org, $recipientUserId, $keepAccess, $actor): VehicleTransferOutcome {
            $this->em->lock($vehicle, LockMode::PESSIMISTIC_WRITE);

            // Stato fresco: tra il voter e il lock un'altra richiesta può aver spostato la proprietà o cambiato ruoli.
            $shares = $this->shareRepo->findByVehicleRefreshed($vehicle);
            $this->assertActorCanManage($vehicle, $actor, $shares);

            $recipientMember = $this->memberRepo->findTransferRecipient($org, $recipientUserId);
            if ($recipientMember === null) {
                throw VehicleTransferException::recipientInvalid();
            }
            $recipient = $recipientMember->getUser();

            $owners = array_values(array_filter(
                $shares,
                static fn (VehicleShare $s): bool => $s->getRole() === ShareRole::ADMIN && $s->isAccepted(),
            ));
            foreach ($owners as $owner) {
                if ($owner->getUser()->getId() === $recipient->getId()) {
                    throw VehicleTransferException::sameOwner();
                }
            }

            // Il primo (il più vecchio) è "il" proprietario precedente; eventuali altri `admin` accettati sono
            // duplicati legacy: scendono a viewer, così dopo il commit ne resta esattamente uno.
            $previous = $owners[0] ?? null;
            foreach ($owners as $owner) {
                if ($owner === $previous && !$keepAccess) {
                    // Fuori anche dalla collezione inversa (cascade persist): altrimenti Doctrine la vedrebbe "rimossa ma raggiungibile".
                    $vehicle->getShares()->removeElement($owner);
                    $this->em->remove($owner);
                } else {
                    $owner->setRole(ShareRole::VIEWER);
                }
            }

            $this->ownership->assign($vehicle, $recipient);
            // Il nuovo proprietario riceve al prossimo invio anche ciò che era già stato notificato al vecchio.
            $this->reminderRepo->resetNotificationState($vehicle);

            return new VehicleTransferOutcome($vehicle, $recipient, $previous?->getUser(), $keepAccess);
        });
    }

    /**
     * Stessa regola di `VehicleVoter::SHARE` (proprietario o owner/admin dell'org) su membership e share riletti.
     *
     * @param list<VehicleShare> $freshShares
     */
    private function assertActorCanManage(Vehicle $vehicle, User $actor, array $freshShares): void
    {
        $membership = $this->memberRepo->findMembership($actor, $vehicle->getOrganization());
        try {
            if ($membership !== null) {
                $this->em->refresh($membership);
            }
        } catch (EntityNotFoundException) {
            $membership = null;
        }

        $isOrgAdmin = $membership !== null && $membership->isAccepted() && $membership->getRole()->isOrgAdmin();
        $shareRole = null;
        foreach ($freshShares as $share) {
            if ($share->getUser()->getId() === $actor->getId() && $share->isAccepted()) {
                $shareRole = $share->getRole();
            }
        }

        $accepted = $membership !== null && $membership->isAccepted();
        $level = $accepted ? VehicleAccessChecker::resolveLevel($isOrgAdmin, $shareRole) : null;
        if (!VehicleAccessChecker::permissionsForLevel($level)['canShare']) {
            throw VehicleTransferException::ownershipChanged();
        }
    }
}
