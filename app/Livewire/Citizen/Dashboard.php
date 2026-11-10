<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Support\Auth\AuthenticatedUserRedirector;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class Dashboard extends Component
{
    public function mount(PermissionChecker $permissions, AuthenticatedUserRedirector $redirector): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'profile.view'), 403);

        if ($actor->hasRole('admin', 'petugas', 'bendahara') && ! $actor->hasRole('warga')) {
            $this->redirect($redirector->dashboardUrl($actor), navigate: true);

            return;
        }
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        // Load ledger account with entries and holds
        $ledgerAccount = LedgerAccount::query()
            ->with(['entries', 'holds'])
            ->where('user_id', $actor->id)
            ->first();

        $availableBalance = $ledgerAccount?->availableBalance() ?? 0;
        $heldBalance = $ledgerAccount
            ? (int) $ledgerAccount->holds()->where('status', BalanceHold::STATUS_ACTIVE)->sum('amount')
            : 0;
        $totalIn = $ledgerAccount
            ? (int) $ledgerAccount->entries()->where('direction', LedgerEntry::DIRECTION_IN)->sum('amount')
            : 0;
        $totalOut = $ledgerAccount
            ? (int) $ledgerAccount->entries()->where('direction', LedgerEntry::DIRECTION_OUT)->sum('amount')
            : 0;

        // Recent deposits (last 5)
        $recentDeposits = Deposit::query()
            ->with('correction')
            ->where('customer_id', $actor->id)
            ->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])
            ->latest('occurred_at')
            ->take(5)
            ->get();

        // Active pickup requests
        $activePickups = PickupRequest::query()
            ->with('assignedStaff')
            ->where('customer_id', $actor->id)
            ->whereNotIn('status', ['selesai', 'dibatalkan', 'ditolak'])
            ->latest()
            ->take(3)
            ->get();

        // Active withdrawal requests
        $activeWithdrawals = WithdrawalRequest::query()
            ->with('payer')
            ->where('customer_id', $actor->id)
            ->whereNotIn('status', [
                WithdrawalStatus::Paid->value,
                WithdrawalStatus::Cancelled->value,
            ])
            ->latest()
            ->take(3)
            ->get();

        $activeGroceries = GroceryRedemption::query()
            ->with('claimant')
            ->where('customer_id', $actor->id)
            ->whereNotIn('status', [
                GroceryStatus::Completed->value,
                GroceryStatus::Unavailable->value,
                GroceryStatus::Cancelled->value,
            ])
            ->latest()
            ->take(3)
            ->get();

        $activePayouts = config('payouts.enabled')
            ? SandboxPayout::query()->where('customer_id', $actor->id)->whereNotIn('status', ['succeeded', 'failed', 'cancelled', 'reversed'])->latest('id')->limit(3)->get()
            : collect();

        return view('livewire.citizen.dashboard', [
            'actorName' => $actor->name,
            'availableBalance' => $availableBalance,
            'heldBalance' => $heldBalance,
            'totalIn' => $totalIn,
            'totalOut' => $totalOut,
            'hasLedger' => $ledgerAccount !== null,
            'recentDeposits' => $recentDeposits,
            'activePickups' => $activePickups,
            'activeWithdrawals' => $activeWithdrawals,
            'activeGroceries' => $activeGroceries,
            'activePayouts' => $activePayouts,
            'groceryHref' => route('citizen.grocery.create'),
        ]);
    }
}
