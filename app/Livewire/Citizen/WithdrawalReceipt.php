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
final class WithdrawalReceipt extends Component
{
    public WithdrawalRequest $withdrawal;

    public function mount(WithdrawalRequest $withdrawal, WithdrawalService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($withdrawal->customer_id === $actor->id && $service->canView($actor, $withdrawal), 404);
        abort_unless($withdrawal->status->value === 'sudah_dibayar', 404);
        $this->withdrawal = $withdrawal->load(['customer.customerProfile', 'payer', 'proofMedia', 'receiptLedgerEntry']);
    }

    public function render(): View
    {
        return view('livewire.citizen.withdrawal-receipt');
    }
}
