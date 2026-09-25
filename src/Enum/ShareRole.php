<?php

declare(strict_types=1);

namespace App\Enum;

enum ShareRole: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function canEdit(): bool
    {
        return match ($this) {
            self::ADMIN, self::EDITOR => true,
            self::VIEWER => false,
        };
    }

    public function canDelete(): bool
    {
        return $this === self::ADMIN;
    }
}
