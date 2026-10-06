<?php

declare(strict_types=1);

namespace App\Tests\Unit\Validator;

use App\Validator\ViolationKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationInterface;
use Symfony\Component\Validator\Validation;

/** Ogni vincolo con il messaggio di default diventa la chiave `common.*` che usa anche zod nel browser. */
final class ViolationKeyTest extends TestCase
{
    /** @return iterable<string, array{mixed, Constraint, string}> */
    public static function defaults(): iterable
    {
        yield 'NotBlank' => ['', new Assert\NotBlank(), 'common.required'];
        yield 'NotNull' => [null, new Assert\NotNull(), 'common.required'];
        yield 'Length troppo corto' => ['a', new Assert\Length(min: 3), 'common.too_short'];
        yield 'Length troppo lungo' => [str_repeat('a', 11), new Assert\Length(max: 10), 'common.too_long'];
        yield 'Count troppo pochi' => [[], new Assert\Count(min: 1), 'common.too_short'];
        yield 'Count troppi' => [[1, 2, 3], new Assert\Count(max: 2), 'common.too_long'];
        yield 'Positive' => [0, new Assert\Positive(), 'common.must_be_positive'];
        yield 'PositiveOrZero' => [-1, new Assert\PositiveOrZero(), 'common.too_small'];
        yield 'Range sotto il minimo' => [-1, new Assert\Range(min: 0), 'common.too_small'];
        yield 'Range sopra il massimo' => [11, new Assert\Range(max: 10), 'common.too_big'];
        yield 'Range fuori intervallo' => [11, new Assert\Range(min: 0, max: 10), 'common.invalid_value'];
        yield 'Email' => ['non-una-email', new Assert\Email(), 'account.email.invalid'];
        yield 'Date' => ['2026-13-45', new Assert\Date(), 'common.invalid_date'];
        yield 'Choice' => ['x', new Assert\Choice(choices: ['a', 'b']), 'common.invalid_value'];
        yield 'Regex' => ['A!', new Assert\Regex('/^[a-z]+$/'), 'common.invalid_value'];
        yield 'Type' => ['abc', new Assert\Type('int'), 'common.invalid_value'];
    }

    #[DataProvider('defaults')]
    public function testDefaultMessageBecomesACommonKey(mixed $value, Constraint $constraint, string $expected): void
    {
        self::assertSame($expected, ViolationKey::of(self::onlyViolation($value, $constraint)));
    }

    public function testAnExplicitI18nKeyIsKept(): void
    {
        $violation = self::onlyViolation(3000, new Assert\Range(min: 1900, max: 2100, notInRangeMessage: 'vehicle.year.out_of_range'));

        self::assertSame('vehicle.year.out_of_range', ViolationKey::of($violation));
    }

    public function testAConstraintWeDoNotMapKeepsItsMessage(): void
    {
        $violation = self::onlyViolation('x', new Assert\Uuid());

        self::assertSame((string) $violation->getMessage(), ViolationKey::of($violation));
    }

    public function testTypeMismatchFromThePayloadResolverHasNoCodeButIsRecognised(): void
    {
        // Così le crea RequestPayloadValueResolver quando il serializer non converte il valore.
        $violation = new ConstraintViolation('This value should be of type int.', 'This value should be of type {{ type }}.', ['{{ type }}' => 'int'], null, 'year', null);

        self::assertSame('common.invalid_value', ViolationKey::of($violation));
    }

    public function testUnexpectedTypeWithoutExpectedTypesIsRecognisedToo(): void
    {
        // Valore fuori da un enum backed: il resolver non ha "tipi attesi" e usa il modello generico.
        $violation = new ConstraintViolation('This value was of an unexpected type.', 'This value was of an unexpected type.', [], null, 'type', null);

        self::assertSame('common.invalid_value', ViolationKey::of($violation));
    }

    public function testErrorsPairEachViolationWithItsField(): void
    {
        $violations = Validation::createValidator()->validate('', new Assert\NotBlank());

        self::assertSame([['field' => '', 'message' => 'common.required']], ViolationKey::errors($violations));
    }

    private static function onlyViolation(mixed $value, Constraint $constraint): ConstraintViolationInterface
    {
        $violations = iterator_to_array(Validation::createValidator()->validate($value, $constraint));
        self::assertCount(1, $violations);

        return $violations[0];
    }
}
