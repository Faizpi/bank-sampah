<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use App\Support\WeightFormatter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class WeightFormatterTest extends TestCase
{
    /** @return iterable<string, array{string|int|float|null, string}> */
    public static function values(): iterable
    {
        yield 'whole number' => ['1', '1'];
        yield 'one decimal' => ['1.2', '1,2'];
        yield 'two decimals' => ['1.25', '1,25'];
        yield 'trailing zero' => ['1.250', '1,25'];
        yield 'rounds down' => ['1.256', '1,26'];
        yield 'small value' => ['0.005', '0,01'];
        yield 'large value' => ['1234567890.125', '1.234.567.890,13'];
        yield 'blank' => ['', '—'];
        yield 'null' => [null, '—'];
    }

    #[DataProvider('values')]
    public function test_formats_kg_for_indonesian_presentation(string|int|float|null $value, string $expected): void
    {
        self::assertSame($expected, WeightFormatter::format($value));
    }

    #[DataProvider('fixedTwoDecimalValues')]
    public function test_formats_report_weights_with_exactly_two_decimals(string|int|float $value, string $expected): void
    {
        self::assertSame($expected, WeightFormatter::format($value, fixedTwoDecimals: true));
    }

    /** @return iterable<string, array{string|int|float, string}> */
    public static function fixedTwoDecimalValues(): iterable
    {
        yield 'whole number' => ['2', '2,00'];
        yield 'one decimal' => ['2.5', '2,50'];
        yield 'rounded three decimals' => ['2.505', '2,51'];
    }
}
