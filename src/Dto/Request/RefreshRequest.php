<?php

declare(strict_types=1);

namespace App\Dto\Request;

final class RefreshRequest
{
    public function __construct(
        /** Opzionale: presente solo per i client mobile che inviano il refresh nel body */
        public ?string $refreshToken = null,
    ) {
    }
}
