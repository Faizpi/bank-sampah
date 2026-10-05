<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class WithdrawalShow extends Component
{
    public WithdrawalRequest $withdrawal;

    public function mount(WithdrawalRequest $withdrawal, WithdrawalService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $withdrawal), 404);
        $this->withdrawal = $withdrawal->load(['customer', 'balanceHold', 'statusHistory.actor', 'payer', 'proofMedia', 'receiptLedgerEntry']);
    }

    public function cancel(WithdrawalService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->withdrawal = $service->cancel($actor, $this->withdrawal, 'Warga membatalkan pengajuan sebelum pembayaran.');
        session()->flash('success', 'Pengajuan pencairan dibatalkan dan dana yang ditahan dikembalikan.');
    }

    public function render(): View
    {
        return view('livewire.citizen.withdrawal-show');
    }
}
