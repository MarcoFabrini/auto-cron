<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

final class DecimalStringValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof DecimalString) {
            throw new UnexpectedTypeException($constraint, DecimalString::class);
        }

        if ($value === null || $value === '') {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedValueException($value, 'string');
        }

        $pattern = sprintf('/^\d+(\.\d{1,%d})?$/', $constraint->maxDecimals);
        if (preg_match($pattern, $value) !== 1) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
