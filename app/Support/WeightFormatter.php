<?php

declare(strict_types=1);

namespace App\Support;

final class WeightFormatter
{
    public static function format(string|int|float|null $value): string
    {
        if ($value === null || $value === '') {
            return '—';
        }

        $normalized = str_replace(',', '.', (string) $value);
        if (preg_match('/^(\d+)(?:\.(\d+))?$/D', $normalized, $matches) !== 1) {
            return '—';
        }

        $whole = ltrim($matches[1], '0') ?: '0';
        $fraction = $matches[2] ?? '';
        $hundredths = str_pad(substr($fraction, 0, 2), 2, '0');
        if (isset($fraction[2]) && $fraction[2] >= '5') {
            $rounded = (int) $hundredths + 1;
            if ($rounded === 100) {
                $whole = (string) ((int) $whole + 1);
                $hundredths = '00';
            } else {
                $hundredths = str_pad((string) $rounded, 2, '0', STR_PAD_LEFT);
            }
        }

        $whole = number_format((int) $whole, 0, ',', '.');
        $formattedFraction = rtrim($hundredths, '0');

        return $whole.($formattedFraction === '' ? '' : ','.$formattedFraction);
    }
}
