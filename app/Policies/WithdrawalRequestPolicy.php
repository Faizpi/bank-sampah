<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;

final readonly class WithdrawalRequestPolicy
{
    public function __construct(private PermissionChecker $permissions, private WithdrawalService $withdrawals) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'withdrawal.view');
    }

    public function view(User $actor, WithdrawalRequest $withdrawal): bool
    {
        return $this->withdrawals->canView($actor, $withdrawal);
    }

    public function approve(User $actor, WithdrawalRequest $withdrawal): bool
    {
        return $this->permissions->allows($actor, 'withdrawal.approve') && $this->withdrawals->canView($actor, $withdrawal);
    }

    public function pay(User $actor, WithdrawalRequest $withdrawal): bool
    {
        return $this->permissions->allows($actor, 'withdrawal.pay') && $withdrawal->payer_id === $actor->id;
    }

    public function cancel(User $actor, WithdrawalRequest $withdrawal): bool
    {
        return $this->permissions->allows($actor, 'withdrawal.cancel') && $this->withdrawals->canView($actor, $withdrawal);
    }
}
