<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Trasferimento di proprietà rifiutato: porta la chiave i18n (`title` del Problem Details) e lo status
 * HTTP, che il controller mappa con `problem()`. Le chiavi vivono sotto `errors.transfer.*` nei locali.
 */
final class VehicleTransferException extends \RuntimeException
{
    /** Id sconosciuto, di un'altra org, non accettato o anonimizzato: un'unica risposta, nessuna enumerazione. */
    public const RECIPIENT_INVALID = 'transfer.recipient_invalid';
    public const SAME_OWNER = 'transfer.same_owner';
    /** Con lo stato riletto dopo il lock chi chiede non può più gestire il veicolo (la proprietà si è spostata). */
    public const OWNERSHIP_CHANGED = 'transfer.ownership_changed';

    public function __construct(
        public readonly string $key,
        public readonly int $status,
    ) {
        parent::__construct($key);
    }

    public static function recipientInvalid(): self
    {
        return new self(self::RECIPIENT_INVALID, 422);
    }

    public static function sameOwner(): self
    {
        return new self(self::SAME_OWNER, 409);
    }

    public static function ownershipChanged(): self
    {
        return new self(self::OWNERSHIP_CHANGED, 409);
    }
}
