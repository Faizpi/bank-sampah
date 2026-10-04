<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Shared\Weight;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class DepositPublicPresenter
{
    /** @return array{number: string, date: string, weight_kg: string, value: int, status: string} */
    public function present(Deposit $deposit): array
    {
        if (! $deposit->isFinal() || $deposit->status === Deposit::STATUS_REVERSED) {
            throw ValidationException::withMessages(['deposit' => 'Bukti tidak tersedia.']);
        }

        return [
            'number' => (string) $deposit->deposit_number,
            'date' => CarbonImmutable::parse((string) $deposit->occurred_at)->toIso8601String(),
            'weight_kg' => Weight::fromDecimal((string) $deposit->total_weight_kg)->decimal(),
            'value' => (int) $deposit->total_value,
            'status' => (string) $deposit->status,
        ];
    }
}
