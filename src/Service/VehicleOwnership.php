<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Vehicle;
use App\Entity\VehicleShare;
use App\Enum\ShareRole;
use App\Repository\VehicleShareRepository;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Cos'è la proprietà di un veicolo: lo share `admin` accettato. Questo è l'unico punto che lo scrive
 * per un utente già esistente nei passaggi di mano: la creazione del veicolo (VehicleController), la
 * rimozione di un membro ({@see OrganizationMemberRemover}) e il trasferimento
 * ({@see VehicleOwnershipTransfer}) passano tutte da qui.
 *
 * Non fa flush né tocca gli altri share: transazione e regole del contorno sono del chiamante.
 */
final class VehicleOwnership
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly VehicleShareRepository $shareRepo,
    ) {
    }

    /**
     * Rende $newOwner proprietario di $vehicle: promuove sul posto uno share esistente (l'unicità
     * `(vehicle, user)` vieta un secondo record) oppure ne crea uno, e lo marca accettato.
     */
    public function assign(Vehicle $vehicle, User $newOwner): VehicleShare
    {
        $share = $this->shareRepo->findForUserAndVehicle($newOwner, $vehicle);
        if ($share === null) {
            $share = (new VehicleShare())
                ->setVehicle($vehicle)
                ->setUser($newOwner)
                ->setInvitedBy($newOwner);
            $this->em->persist($share);
        }
        $share->setRole(ShareRole::ADMIN);
        if (!$share->isAccepted()) {
            $share->setAcceptedAt(new \DateTimeImmutable());
        }

        return $share;
    }
}
