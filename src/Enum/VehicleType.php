<?php

declare(strict_types=1);

namespace App\Enum;

enum VehicleType: string
{
    case CAR = 'car';
    case MOTORCYCLE = 'motorcycle';
    case VAN = 'van';
    case CAMPER = 'camper';
    case BOAT = 'boat';
    case TRUCK = 'truck';
    case BICYCLE = 'bicycle';
    case SCOOTER = 'scooter';
    case TRAILER = 'trailer';
    case TRACTOR = 'tractor';
}
