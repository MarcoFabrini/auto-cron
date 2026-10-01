<?php

declare(strict_types=1);

namespace App\Service;

/**
 * "Oggi" per la logica di calendario (scadenze dei promemoria).
 *
 * Il PHP gira in UTC, ma scadenze e utenti sono nel fuso locale dell'istanza (APP_TIMEZONE, default
 * Europe/Rome): tra mezzanotte e le 1-2 di notte "oggi" in UTC è ancora ieri, e un promemoria in
 * scadenza oggi risulterebbe "a un giorno" con la notifica in ritardo di un giorno.
 */
final class AppClock
{
    public function __construct(private readonly string $timezone = 'Europe/Rome')
    {
    }

    /**
     * Il giorno di calendario corrente nel fuso dell'istanza, come mezzanotte nel fuso di default di PHP:
     * lo stesso formato delle date (Y-m-d) che Doctrine legge dal DB, quindi confrontabile e sottraibile
     * direttamente con `dueDate`.
     */
    public function today(?\DateTimeImmutable $now = null): \DateTimeImmutable
    {
        $local = ($now ?? new \DateTimeImmutable())->setTimezone(new \DateTimeZone($this->timezone));

        return new \DateTimeImmutable($local->format('Y-m-d'));
    }
}
