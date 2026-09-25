<?php

declare(strict_types=1);

namespace App\Enum;

enum AuditAction: string
{
    case CREATED = 'created';
    case UPDATED = 'updated';
    case DELETED = 'deleted';
}
