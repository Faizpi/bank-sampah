<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class WithdrawalRequestForm extends Component
{
    public string $amount = '';

    public string $pickupLocation = '';

    public string $pickupDate = '';

    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->pickupDate = today()->addDay()->toDateString();
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function submit(WithdrawalService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $withdrawal = $service->request($actor, [
            'amount' => $this->amount,
            'pickup_location' => $this->pickupLocation,
            'pickup_date' => $this->pickupDate,
        ], $this->idempotencyKey);
        session()->flash('success', 'Pengajuan pencairan berhasil dibuat dan saldo ditahan sementara.');
        $this->redirectRoute('citizen.withdrawal.show', ['withdrawal' => $withdrawal], navigate: true);
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        $ledgerAccount = LedgerAccount::query()
            ->where('user_id', $actor->id)
            ->first();

        return view('livewire.citizen.withdrawal-request-form', [
            'availableBalance' => $ledgerAccount?->availableBalance() ?? 0,
        ]);
    }
}
