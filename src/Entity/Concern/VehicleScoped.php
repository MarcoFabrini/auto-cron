<?php

declare(strict_types=1);

namespace App\Entity\Concern;

use App\Entity\Vehicle;

/**
 * Marca un'entità come "appesa" a un Vehicle, così i Voter generici
 * possono delegare a `VehicleAccessChecker`.
 */
interface VehicleScoped
{
    public function getVehicle(): Vehicle;
}
