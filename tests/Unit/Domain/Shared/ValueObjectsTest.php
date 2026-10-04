<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Shared;

use App\Domain\Shared\BusinessDate;
use App\Domain\Shared\InvalidValue;
use App\Domain\Shared\Money;
use App\Domain\Shared\OpaqueToken;
use App\Domain\Shared\Weight;
use DateTimeImmutable;
use DateTimeZone;
use OverflowException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ValueObjectsTest extends TestCase
{
    public function test_money_uses_non_negative_integer_rupiah_and_checked_arithmetic(): void
    {
        $money = Money::rupiah(1_250);

        self::assertSame(1_250, $money->amount());
        self::assertSame(1_300, $money->add(Money::rupiah(50))->amount());
        self::assertSame(1_200, $money->subtract(Money::rupiah(50))->amount());
        self::assertTrue($money->equals(Money::rupiah(1_250)));
        self::assertFalse($money->equals(Money::rupiah(1_251)));
    }

    public function test_money_rejects_negative_values_and_underflow(): void
    {
        $this->expectException(InvalidValue::class);
        Money::rupiah(-1);
    }

    public function test_money_exposes_only_an_integer_type_boundary(): void
    {
        $parameter = (new \ReflectionMethod(Money::class, 'rupiah'))->getParameters()[0];

        self::assertSame('int', (string) $parameter->getType());
    }

    public function test_money_accepts_zero_as_a_non_negative_rupiah_value(): void
    {
        self::assertSame(0, Money::rupiah(0)->amount());
    }

    public function test_money_rejects_arithmetic_overflow(): void
    {
        $this->expectException(OverflowException::class);
        Money::rupiah(PHP_INT_MAX)->add(Money::rupiah(1));
    }

    public function test_money_rejects_subtraction_below_zero(): void
    {
        $this->expectException(InvalidValue::class);
        Money::rupiah(1)->subtract(Money::rupiah(2));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidWeights(): iterable
    {
        foreach (['', '0', '0.000', '-1', '+1', ' 1', '1 ', '1,5', '1.2345', '1e3', 'NaN', '.5', '1.', '01.000'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('invalidWeights')]
    public function test_weight_rejects_non_canonical_non_positive_or_over_precise_input(string $value): void
    {
        $this->expectException(InvalidValue::class);
        Weight::fromDecimal($value);
    }

    public function test_weight_preserves_exact_grams_and_canonical_decimal(): void
    {
        self::assertSame(1, Weight::fromDecimal('0.001')->grams());
        self::assertSame('12.34', Weight::fromDecimal('12.340')->decimal());
        self::assertSame('12', Weight::fromGrams(12_000)->decimal());
        self::assertTrue(Weight::fromDecimal('1.250')->equals(Weight::fromGrams(1_250)));
    }

    public function test_weight_rejects_float_at_the_type_boundary(): void
    {
        $this->expectException(\TypeError::class);
        /** @phpstan-ignore-next-line Deliberately verifies that binary floats cannot enter the domain. */
        Weight::fromDecimal(1.25);
    }

    public function test_weight_calculates_each_subtotal_once_with_half_up_rounding(): void
    {
        self::assertSame(1, Weight::fromDecimal('0.001')->subtotal(Money::rupiah(500))->amount());
        self::assertSame(0, Weight::fromDecimal('0.001')->subtotal(Money::rupiah(499))->amount());
        self::assertSame(12_345, Weight::fromDecimal('1.234')->subtotal(Money::rupiah(10_004))->amount());
    }

    public function test_weight_subtotal_detects_overflow(): void
    {
        $this->expectException(OverflowException::class);
        Weight::fromDecimal('2')->subtotal(Money::rupiah(PHP_INT_MAX));
    }

    /** @return iterable<string, array{string}> */
    public static function invalidDates(): iterable
    {
        foreach (['', '2024-2-01', '01-02-2024', '2023-02-29', '2024-02-30', '2024-01-01T00:00:00+07:00', ' 2024-01-01'] as $value) {
            yield $value => [$value];
        }
    }

    #[DataProvider('invalidDates')]
    public function test_business_date_strictly_rejects_invalid_or_ambiguous_dates(string $value): void
    {
        $this->expectException(InvalidValue::class);
        BusinessDate::fromString($value);
    }

    public function test_business_date_is_immutable_and_uses_jakarta_day_semantics(): void
    {
        $date = BusinessDate::fromString('2024-02-29');
        $instant = new DateTimeImmutable('2024-01-01 17:30:00', new DateTimeZone('UTC'));

        self::assertSame('2024-02-29', $date->value());
        self::assertSame('2024-01-02', BusinessDate::fromInstant($instant)->value());
        self::assertSame('2024-03-01', $date->nextDay()->value());
        self::assertSame('2024-02-29', $date->value());
        self::assertTrue($date->equals(BusinessDate::fromString('2024-02-29')));
        self::assertFalse($date->equals(BusinessDate::fromString('2024-03-01')));
    }

    public function test_opaque_token_is_case_sensitive_exact_and_constant_time_comparable(): void
    {
        $token = OpaqueToken::fromEncoded('AbC_123-xyz');

        self::assertTrue($token->equals(OpaqueToken::fromEncoded('AbC_123-xyz')));
        self::assertFalse($token->equals(OpaqueToken::fromEncoded('abc_123-xyz')));
        self::assertSame('AbC_123-xyz', $token->revealForPersistence());
    }

    public function test_opaque_token_rejects_empty_whitespace_or_control_characters_without_normalizing(): void
    {
        $rejected = 0;
        foreach (['', ' token', 'token ', "tok\nen"] as $invalid) {
            try {
                OpaqueToken::fromEncoded($invalid);
                self::fail('Invalid token was accepted.');
            } catch (InvalidValue) {
                $rejected++;
            }
        }

        self::assertSame(4, $rejected);
    }

    public function test_opaque_token_generation_is_random_and_safe_for_urls(): void
    {
        $first = OpaqueToken::generate();
        $second = OpaqueToken::generate();

        self::assertNotSame($first->revealForPersistence(), $second->revealForPersistence());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $first->revealForPersistence());
    }

    public function test_opaque_token_never_leaks_through_string_debug_or_json_surfaces(): void
    {
        $secret = 'highly-sensitive-token';
        $token = OpaqueToken::fromEncoded($secret);

        self::assertSame('[REDACTED]', (string) $token);
        self::assertSame(['value' => '[REDACTED]'], $token->__debugInfo());
        self::assertSame('"[REDACTED]"', json_encode($token, JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($secret, var_export($token, true));
    }
}
