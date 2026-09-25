<?php

declare(strict_types=1);

namespace App\Enum;

enum RecurringPeriod: string
{
    case WEEKLY = 'weekly';
    case MONTHLY = 'monthly';
    case QUARTERLY = 'quarterly';
    /** Semestrale (es. assicurazione frazionata) */
    case SEMIANNUAL = 'semiannual';
    case YEARLY = 'yearly';
    /** Biennale (es. revisione MCTC) */
    case BIENNIAL = 'biennial';
}
