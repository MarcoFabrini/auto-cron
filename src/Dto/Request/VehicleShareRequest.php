<?php

declare(strict_types=1);

namespace App\Dto\Request;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Condivisione di un veicolo con uno o più membri dell'org, indicati per id
 * (scelti dall'elenco di GET /api/vehicles/{id}/share-candidates: niente email
 * digitata, quindi niente modo di scoprire quali account esistono). Nessun
 * ruolo: gli share sono sempre in sola lettura (viewer).
 */
final class VehicleShareRequest
{
    /**
     * @param list<int> $userIds
     */
    public function __construct(
        #[Assert\Count(min: 1, max: 50)]
        #[Assert\All([new Assert\Type('int'), new Assert\Positive()])]
        public array $userIds = [],
    ) {
    }
}
