<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;

final class TelegramRecordAccess
{
    /** @param array<string, mixed> $guard */
    public function allows(User $actor, array $guard): bool
    {
        $kind = $guard['kind'] ?? '';
        $id = (int) ($guard['id'] ?? 0);
        if ($kind === 'deposit') {
            return Deposit::query()->whereKey($id)->where($actor->hasRole('warga') ? 'customer_id' : 'staff_id', $actor->id)->exists();
        }
        $query = match ($kind) {
            'pickup' => app(PickupService::class)->visibleFor($actor),
            'cash' => app(WithdrawalService::class)->visibleFor($actor),
            'grocery' => app(GroceryService::class)->visibleFor($actor),
            default => null,
        };
        if ($query === null) {
            return false;
        }
        if (isset($guard['status'])) {
            $query->where('status', $guard['status']);
        }

        return $query->whereKey($id)->exists();
    }
}
