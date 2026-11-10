<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Payouts\Models\SandboxPayout;
use Illuminate\Validation\ValidationException;

final class PayoutIntegrity
{
    public function hold(SandboxPayout $payout): BalanceHold
    {
        $hold = $payout->balanceHold()->with('account')->firstOrFail();
        if ($hold->source_type !== SandboxPayout::class || $hold->source_id !== $payout->id || $hold->amount !== $payout->amount || $hold->account->user_id !== $payout->customer_id || $hold->account->currency !== 'IDR') {
            throw ValidationException::withMessages(['payout' => 'Sumber saldo tertahan tidak sesuai pencairan.']);
        }

        return $hold;
    }
}
