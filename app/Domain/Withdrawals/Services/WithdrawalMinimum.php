<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Services;

use App\Domain\Operations\Models\OperationalSetting;
use Illuminate\Support\Facades\Schema;

final class WithdrawalMinimum
{
    public function amount(): int
    {
        $fallback = min(1_000_000, max(5_000, (int) config('app.withdrawal_minimum_amount', 10000)));
        if (! Schema::hasTable('settings')) {
            return $fallback;
        }
        $value = OperationalSetting::query()->where('key', 'withdrawal_minimum_amount')->value('value');

        return is_string($value) && ctype_digit($value) && (int) $value >= 5000 && (int) $value <= 1000000
            ? (int) $value : $fallback;
    }
}
