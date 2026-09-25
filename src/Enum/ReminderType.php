<?php

declare(strict_types=1);

namespace App\Enum;

enum ReminderType: string
{
    /** Revisione MCTC (biennale/annuale) */
    case INSPECTION = 'inspection';
    case INSURANCE = 'insurance';
    /** Bollo auto */
    case ROAD_TAX = 'road_tax';
    /** Tagliando ufficiale concessionario */
    case SERVICE = 'service';
    /** Cambio olio km-based */
    case OIL_CHANGE = 'oil_change';
    case TIRES = 'tires';
    case BATTERY = 'battery';
    /** Patente (utente, non veicolo — utile comunque) */
    case LICENSE = 'license';
    /** Estintore (camper, barca — scadenza obbligatoria) */
    case EXTINGUISHER = 'extinguisher';
    case CUSTOM = 'custom';
}
