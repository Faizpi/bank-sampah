<?php

declare(strict_types=1);

namespace App\Support;

final class LocalInput
{
    public static function field(mixed &$row, string|int $field, string $format): void
    {
        if (is_array($row) && is_string($row[$field] ?? null)) {
            $row[$field] = $format === 'rupiah' ? self::rupiah($row[$field]) : self::decimal($row[$field]);
        }
    }

    public static function decimal(string $value): string
    {
        $value = trim($value);

        return preg_match('/^\d+,\d{1,3}$/', $value) === 1 ? str_replace(',', '.', $value) : $value;
    }

    public static function rupiah(string $value): string
    {
        $value = trim(preg_replace('/^Rp\s*/i', '', trim($value)) ?? $value);

        return preg_match('/^\d{1,3}(?:\.\d{3})+$/', $value) === 1 ? str_replace('.', '', $value) : $value;
    }
}
