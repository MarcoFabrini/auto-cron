<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Trasferimento della proprietà di un veicolo a un membro dell'org, indicato per id (scelto dall'elenco
 * di GET /api/vehicles/{id}/transfer-candidates). `keepAccess`: il precedente proprietario resta con
 * uno share in sola lettura (default) oppure esce del tutto.
 */
final class VehicleTransferRequest
{
    public function __construct(
        #[Assert\Positive]
        public int $userId,
        public bool $keepAccess = true,
    ) {
    }
}
