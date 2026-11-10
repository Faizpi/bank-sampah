<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use Illuminate\Validation\ValidationException;

final class PayoutChannels
{
    /** @return array<string, array{name: string, wallet: bool, logo: string, routing: string, ready: bool}> */
    public function all(): array
    {
        $rows = [];
        foreach (['bca' => ['BCA', false], 'mandiri' => ['Mandiri', false], 'bri' => ['BRI', false], 'bni' => ['BNI', false], 'dana' => ['DANA', true], 'gopay' => ['GoPay', true]] as $id => [$name, $wallet]) {
            $routing = trim((string) config('payouts.routing.'.$id));
            $rows[$id] = ['name' => $name, 'wallet' => $wallet, 'logo' => 'images/payouts/'.$id.($id === 'gopay' ? '.webp' : '.svg'), 'routing' => $routing, 'ready' => $routing !== ''];
        }

        return $rows;
    }

    /** @return array{name: string, wallet: bool, logo: string, routing: string, ready: bool} */
    public function get(string $id): array
    {
        $row = $this->all()[$id] ?? null;
        if ($row === null || ! $row['ready']) {
            throw ValidationException::withMessages(['channel' => 'Tujuan pencairan belum tersedia untuk akun uji ini.']);
        }

        return $row;
    }

    public function normalizeNumber(string $channel, string $value): string
    {
        $row = $this->get($channel);
        $value = trim($value);
        if ($row['wallet']) {
            $value = preg_replace('/[\s()-]+/', '', $value) ?? '';
            if (str_starts_with($value, '+62')) {
                $value = '0'.substr($value, 3);
            } elseif (str_starts_with($value, '62')) {
                $value = '0'.substr($value, 2);
            }
            $valid = preg_match('/^08[0-9]{8,11}$/D', $value) === 1;
        } else {
            $valid = preg_match('/^[0-9]{7,17}$/D', $value) === 1;
        }
        if (! $valid) {
            throw ValidationException::withMessages(['number' => $row['wallet'] ? 'Isi nomor dompet digital, contoh 081234567890.' : 'Isi nomor rekening berupa 7 sampai 17 digit.']);
        }

        return $value;
    }
}
