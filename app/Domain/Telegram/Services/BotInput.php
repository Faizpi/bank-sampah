<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Shared\Weight;
use App\Support\LocalInput;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;
use Throwable;

final class BotInput
{
    public static function date(string $value): string
    {
        $format = preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value) ? 'd/m/Y' : 'Y-m-d';
        try {
            $date = CarbonImmutable::createFromFormat('!'.$format, $value, 'Asia/Jakarta');
            if ($date === null || $date->format($format) !== $value) {
                throw new \InvalidArgumentException;
            }

            return $date->toDateString();
        } catch (Throwable) {
            throw ValidationException::withMessages(['date' => 'Tanggal tidak valid. Gunakan DD/MM/YYYY, contoh 25/10/2026.']);
        }
    }

    public static function weight(string $value): string
    {
        return Weight::fromDecimal(LocalInput::decimal($value))->decimal();
    }

    public static function money(string $value): string
    {
        $value = LocalInput::rupiah($value);
        if (! ctype_digit($value) || strlen($value) > 15 || (int) $value < 1) {
            throw ValidationException::withMessages(['amount' => 'Nominal tidak valid. Contoh: 25000 atau 25.000.']);
        }

        return $value;
    }

    public static function text(string $value, int $min = 1, int $max = 500): string
    {
        if (mb_strlen(trim($value)) < $min || mb_strlen($value) > $max || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $value)) {
            throw ValidationException::withMessages(['text' => 'Isian belum sesuai. Gunakan '.$min.'–'.$max.' karakter.']);
        }

        return trim($value);
    }

    public static function rupiah(int $value): string
    {
        return 'Rp '.number_format($value, 0, ',', '.');
    }
}
