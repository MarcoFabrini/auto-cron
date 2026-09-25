<?php

declare(strict_types=1);

namespace App\Enum;

enum ExpenseCategory: string
{
    case INSURANCE = 'insurance';
    /** Bollo auto / superbollo */
    case ROAD_TAX = 'road_tax';
    /** Altre tasse non bollo (es. tassa di possesso regionale extra) */
    case TAX = 'tax';
    case PARKING = 'parking';
    case TOLL = 'toll';
    case FINE = 'fine';
    case ACCESSORY = 'accessory';
    /** Rata leasing / finanziamento */
    case FINANCING = 'financing';
    /** Abbonamento Telepass, app parcheggio, ricarica EV */
    case SUBSCRIPTION = 'subscription';
    /** Box / rimessa / posto auto affittato */
    case GARAGE = 'garage';
    /** Lavaggio (alternativa a maintenance.wash quando è spot) */
    case CAR_WASH = 'car_wash';
    /** Furto, atti vandalici, eventi non coperti assicurazione */
    case THEFT_DAMAGE = 'theft_damage';
    case OTHER = 'other';
}
