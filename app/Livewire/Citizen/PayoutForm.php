<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\LocalInput;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class PayoutForm extends Component
{
    public string $channel = '';

    public string $recipientName = '';

    public string $accountNumber = '';

    public string $amount = '';

    public string $serviceAreaId = '';

    #[Locked]
    public string $idempotencyKey = '';

    #[Locked]
    public ?string $reviewHash = null;

    public function mount(): void
    {
        abort_unless(PayoutAccess::configured(), 404);
        $actor = $this->actor();
        $this->idempotencyKey = (string) str()->uuid();
        $areas = app(WithdrawalService::class)->availableAreasFor($actor)->get();
        $this->serviceAreaId = $areas->count() === 1 ? (string) $areas->first()->id : '';
    }

    public function updated(): void
    {
        $this->reviewHash = null;
    }

    public function review(PayoutService $service): void
    {
        $this->resetValidation();
        $quote = $service->quote($this->actor(), $this->data());
        $this->amount = (string) $quote['amount'];
        $this->accountNumber = $quote['number'];
        $this->reviewHash = hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR));
    }

    public function edit(): void
    {
        $this->reviewHash = null;
    }

    public function submit(PayoutService $service): void
    {
        $quote = $service->quote($this->actor(), $this->data());
        if ($this->reviewHash === null || ! hash_equals($this->reviewHash, hash('sha256', json_encode($quote, JSON_THROW_ON_ERROR)))) {
            throw ValidationException::withMessages(['payout' => 'Periksa kembali ringkasan sebelum mengonfirmasi pencairan simulasi.']);
        }
        $row = $service->request($this->actor(), [...$this->data(), 'confirmed' => true], $this->idempotencyKey);
        $this->redirectRoute('citizen.payout.show', ['payout' => $row->id], navigate: true);
    }

    public function render(): View
    {
        abort_unless(PayoutAccess::configured(), 404);
        $actor = $this->actor();

        return view('livewire.citizen.payout-form', [
            'channels' => app(PayoutChannels::class)->all(),
            'serviceAreas' => app(WithdrawalService::class)->availableAreasFor($actor)->get(),
            'balance' => LedgerAccount::query()->where('user_id', $actor->id)->first()?->availableBalance() ?? 0,
        ]);
    }

    /** @return array<string, mixed> */
    private function data(): array
    {
        return ['channel' => $this->channel, 'name' => $this->recipientName, 'number' => $this->accountNumber, 'amount' => LocalInput::rupiah($this->amount), 'service_area_id' => $this->serviceAreaId !== '' ? $this->serviceAreaId : null];
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $actor->hasRole('warga') && $actor->status->value === 'aktif', 403);

        return $actor;
    }
}
