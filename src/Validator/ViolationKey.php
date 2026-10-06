<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolationInterface;

/**
 * Chiave i18n di una violazione, per il campo `message` degli errori 422.
 *
 * Il frontend traduce `errors.<chiave>`: un vincolo con `message: 'vehicle.year.out_of_range'`
 * la porta già in sé; per gli altri (NotBlank, Length, Range…) Symfony avrebbe messo il proprio
 * testo inglese, che il form mostrerebbe com'è. Qui lo si sostituisce guardando il CODICE del
 * vincolo (stabile, indipendente dalla lingua) con le stesse chiavi `common.*` che lo schema zod
 * del browser usa per gli errori equivalenti. Un codice non previsto lascia il messaggio com'è.
 */
final class ViolationKey
{
    /** Chiave i18n tipo `vehicle.not_found`: minuscole, cifre e underscore separati da punti. */
    public const PATTERN = '/^[a-z][a-z0-9_]*(?:\.[a-z0-9_]+)+$/';

    /**
     * Le violazioni di tipo (es. `"year": "duemila"`) le crea il resolver di #[MapRequestPayload] quando
     * il serializer non riesce a convertire il valore: arrivano senza codice, solo con questo modello.
     */
    private const TYPE_MISMATCH_TEMPLATE = 'This value should be of type {{ type }}.';

    /**
     * Stesso caso senza tipi attesi (es. un valore fuori da un enum backed): il resolver usa questo modello
     * più generico, e senza riconoscerlo il form mostrerebbe l'inglese di Symfony.
     */
    private const UNEXPECTED_TYPE_TEMPLATE = 'This value was of an unexpected type.';

    public static function of(ConstraintViolationInterface $violation): string
    {
        $message = (string) $violation->getMessage();
        if (preg_match(self::PATTERN, $message) === 1) {
            return $message;
        }
        if ($violation->getCode() === null && in_array($violation->getMessageTemplate(), [self::TYPE_MISMATCH_TEMPLATE, self::UNEXPECTED_TYPE_TEMPLATE], true)) {
            return 'common.invalid_value';
        }

        return match ($violation->getCode()) {
            Assert\NotBlank::IS_BLANK_ERROR,
            Assert\NotNull::IS_NULL_ERROR => 'common.required',
            Assert\Length::TOO_SHORT_ERROR,
            Assert\Count::TOO_FEW_ERROR => 'common.too_short',
            Assert\Length::TOO_LONG_ERROR,
            Assert\Count::TOO_MANY_ERROR => 'common.too_long',
            Assert\GreaterThan::TOO_LOW_ERROR => 'common.must_be_positive',
            Assert\GreaterThanOrEqual::TOO_LOW_ERROR,
            Assert\Range::TOO_LOW_ERROR => 'common.too_small',
            Assert\Range::TOO_HIGH_ERROR => 'common.too_big',
            Assert\Email::INVALID_FORMAT_ERROR => 'account.email.invalid',
            Assert\Date::INVALID_FORMAT_ERROR,
            Assert\Date::INVALID_DATE_ERROR => 'common.invalid_date',
            Assert\Range::NOT_IN_RANGE_ERROR,
            Assert\Choice::NO_SUCH_CHOICE_ERROR,
            Assert\Regex::REGEX_FAILED_ERROR,
            Assert\Type::INVALID_TYPE_ERROR => 'common.invalid_value',
            default => $message,
        };
    }

    /**
     * @param iterable<ConstraintViolationInterface> $violations
     * @return list<array{field: string, message: string}>
     */
    public static function errors(iterable $violations): array
    {
        $errors = [];
        foreach ($violations as $violation) {
            $errors[] = ['field' => $violation->getPropertyPath(), 'message' => self::of($violation)];
        }

        return $errors;
    }
}
