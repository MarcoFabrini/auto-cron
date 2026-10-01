<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Valida un decimale rappresentato come stringa (es. "120.50"), non float:
 * evita precision loss su importi/quantità. Usata al posto di una Regex
 * scritta a mano in ogni DTO — un solo posto da correggere per tutti i campi.
 */
#[\Attribute(\Attribute::TARGET_PROPERTY | \Attribute::TARGET_METHOD | \Attribute::IS_REPEATABLE)]
final class DecimalString extends Constraint
{
    public string $message = 'validation.decimal_format';
    public int $maxDecimals = 2;
    /** Cifre massime prima del separatore: deve stare nella colonna NUMERIC(p, s) → p - s. */
    public int $maxIntegerDigits = 8;
    public bool $allowZero = false;

    public function __construct(
        int $maxDecimals = 2,
        int $maxIntegerDigits = 8,
        bool $allowZero = false,
        ?string $message = null,
        ?array $groups = null,
        mixed $payload = null,
    ) {
        parent::__construct(null, $groups, $payload);
        $this->maxDecimals = $maxDecimals;
        $this->maxIntegerDigits = $maxIntegerDigits;
        $this->allowZero = $allowZero;
        if ($message !== null) {
            $this->message = $message;
        }
    }
}
