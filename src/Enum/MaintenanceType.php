<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenanceType: string
{
    case OIL_CHANGE = 'oil_change';
    case FILTERS = 'filters';
    case BRAKES = 'brakes';
    case TIRES = 'tires';
    case INSPECTION = 'inspection';
    case REPAIR = 'repair';
    case BATTERY = 'battery';
    /* Cinghia di distribuzione */
    case BELT_CHANGE = 'belt_change';
    /* Candele */
    case SPARK_PLUGS = 'spark_plugs';
    /* Liquido refrigerante */
    case COOLANT = 'coolant';
    /* Frizione */
    case CLUTCH = 'clutch';
    /* Ammortizzatori */
    case SUSPENSION = 'suspension';
    /* Carrozzeria */
    case BODY_WORK = 'body_work';
    case WASH = 'wash';
    case OTHER = 'other';
}
