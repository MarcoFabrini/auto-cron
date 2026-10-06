<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\User;
use App\Entity\Vehicle;

/**
 * Esito di un trasferimento già committato: serve a chi avvisa le parti ({@see MemberNotifier}),
 * che non deve rileggere il DB per sapere chi era il proprietario.
 */
final class VehicleTransferOutcome
{
    public function __construct(
        public readonly Vehicle $vehicle,
        public readonly User $newOwner,
        /** Proprietario precedente; null per un veicolo orfano. */
        public readonly ?User $previousOwner,
        /** Il precedente proprietario resta con uno share in sola lettura (false = share eliminato). */
        public readonly bool $keepAccess,
    ) {
    }
}
