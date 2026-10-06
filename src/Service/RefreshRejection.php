<?php

declare(strict_types=1);

namespace App\Service;

/** Perché un refresh token presentato a `/refresh` non è utilizzabile (vedi {@see RefreshTokenService::classifyRejected()}). */
enum RefreshRejection
{
    /** Sconosciuto, scaduto o revocato per altro (logout, cambio password): un normale 401. */
    case Invalid;

    /** Già ruotato da pochi secondi: due richieste legittime in corsa (due schede, un retry). Nessun effetto. */
    case RaceLost;

    /** Già ruotato da tempo e ripresentato: possibile furto. La famiglia è stata revocata. */
    case Reuse;
}
