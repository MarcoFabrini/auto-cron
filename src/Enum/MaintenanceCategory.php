<?php

declare(strict_types=1);

namespace App\Enum;

enum MaintenanceCategory: string
{
    case SCHEDULED = 'scheduled';
    case UNSCHEDULED = 'unscheduled';
}
