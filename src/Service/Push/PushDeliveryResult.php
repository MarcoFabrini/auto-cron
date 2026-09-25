<?php

declare(strict_types=1);

namespace App\Service\Push;

/**
 * Risultato di un invio push verso una singola subscription.
 * `gone` = la sottoscrizione è espirata/revocata e va cancellata dal DB.
 */
final readonly class PushDeliveryResult
{
    public function __construct(
        public bool $success,
        public bool $gone = false,
        public ?string $errorMessage = null,
    ) {
    }

    public static function ok(): self
    {
        return new self(true);
    }

    public static function failed(string $error, bool $gone = false): self
    {
        return new self(false, $gone, $error);
    }
}
