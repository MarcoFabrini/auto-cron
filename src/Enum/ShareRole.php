<?php

declare(strict_types=1);

namespace App\Enum;

/**
 * Ruolo di una condivisione veicolo.
 * - ADMIN: la proprietà del veicolo (lo share creato per chi lo registra), accesso totale.
 * - VIEWER: condivisione in sola lettura.
 * - EDITOR: valore storico, trattato come VIEWER (le condivisioni non modificano nulla);
 *   resta nell'enum solo per leggere righe vecchie, le nuove non lo usano mai.
 */
enum ShareRole: string
{
    case ADMIN = 'admin';
    case EDITOR = 'editor';
    case VIEWER = 'viewer';

    public function canEdit(): bool
    {
        return $this === self::ADMIN;
    }

    public function canDelete(): bool
    {
        return $this === self::ADMIN;
    }
}
