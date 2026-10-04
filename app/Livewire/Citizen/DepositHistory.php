<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\Deposits\Models\Deposit;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class DepositHistory extends Component
{
    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.view'), 403);
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $deposits = Deposit::query()->with('items')->where('customer_id', $actor->id)->latest('occurred_at')->paginate(10);
        $corrections = TransactionCorrection::query()->whereHas('deposit', static fn ($query) => $query->where('customer_id', $actor->id))->latest('finalized_at')->paginate(10, ['*'], 'corrections');

        return view('livewire.citizen.deposit-history', compact('deposits', 'corrections'));
    }
}
