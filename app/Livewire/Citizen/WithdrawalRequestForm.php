<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Withdrawals\Services\WithdrawalMinimum;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\LocalInput;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class WithdrawalRequestForm extends Component
{
    #[Locked]
    public bool $choosingMethod = false;

    public string $amount = '';

    public string $pickupLocation = '';

    public string $pickupDate = '';

    public string $serviceAreaId = '';

    public string $idempotencyKey = '';

    public function mount(WithdrawalService $service): void
    {
        $this->choosingMethod = PayoutAccess::canViewForm();
        $this->pickupDate = today()->addDay()->toDateString();
        $this->idempotencyKey = (string) str()->uuid();
        /** @var User $actor */
        $actor = auth()->user();
        $areas = $service->availableAreasFor($actor)->get();
        $this->serviceAreaId = $areas->count() === 1 ? (string) $areas->first()->id : '';
    }

    public function chooseCash(): void
    {
        $this->choosingMethod = false;
    }

    public function changeMethod(): void
    {
        abort_unless(PayoutAccess::canViewForm(), 404);
        $this->choosingMethod = true;
        $this->resetValidation();
    }

    public function updatedAmount(): void
    {
        $this->amount = LocalInput::rupiah($this->amount);
        $this->resetValidation('amount');

        if (! ctype_digit($this->amount)) {
            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        if ((int) $this->amount > $this->availableBalance($actor)) {
            $this->addError('amount', 'Nominal melebihi saldo tersedia.');
        }
    }

    public function submit(WithdrawalService $service): void
    {
        if ($this->choosingMethod && PayoutAccess::canViewForm()) {
            $this->addError('request', 'Pilih metode pencairan terlebih dahulu.');

            return;
        }
        $this->amount = LocalInput::rupiah($this->amount);
        $minimumAmount = app(WithdrawalMinimum::class)->amount();
        $validated = $this->validate([
            'amount' => ['required', 'integer', 'min:'.$minimumAmount],
            'pickupLocation' => ['required', 'string', 'min:3', 'max:255'],
            'pickupDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
            'serviceAreaId' => ['nullable', 'integer'],
        ], [
            'amount.required' => 'Isi nominal pencairan.',
            'amount.integer' => 'Nominal harus berupa rupiah tanpa desimal.',
            'amount.min' => 'Nominal pencairan belum memenuhi minimum.',
            'pickupLocation.required' => 'Isi lokasi pengambilan.',
            'pickupDate.required' => 'Pilih tanggal pengambilan.',
            'serviceAreaId.integer' => 'Area layanan tidak valid.',
        ]);
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $withdrawal = $service->request($actor, [
                'amount' => $this->amount,
                'pickup_location' => $this->pickupLocation,
                'pickup_date' => $this->pickupDate,
                'service_area_id' => $validated['serviceAreaId'] ?? null,
            ], $this->idempotencyKey);
        } catch (ValidationException $exception) {
            $this->presentRequestErrors($exception);

            return;
        }

        session()->flash('success', 'Pengajuan pencairan berhasil dibuat dan saldo ditahan sementara.');
        $this->redirectRoute('citizen.withdrawal.show', ['withdrawal' => $withdrawal], navigate: true);
    }

    public function render(WithdrawalService $service): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.citizen.withdrawal-request-form', [
            'digitalAvailable' => PayoutAccess::canViewForm(),
            'availableBalance' => $this->availableBalance($actor),
            'serviceAreas' => $service->availableAreasFor($actor)->get(),
        ]);
    }

    private function availableBalance(User $actor): int
    {
        return LedgerAccount::query()
            ->where('user_id', $actor->id)
            ->first()
            ?->availableBalance() ?? 0;
    }

    private function presentRequestErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $target = match ($field) {
                'amount', 'balance' => 'amount',
                'pickup_location' => 'pickupLocation',
                'pickup_date' => 'pickupDate',
                'service_area_id' => 'serviceAreaId',
                default => 'request',
            };

            $this->addError($target, $messages[0] ?? 'Pengajuan pencairan tidak dapat diproses.');
        }
    }
}
