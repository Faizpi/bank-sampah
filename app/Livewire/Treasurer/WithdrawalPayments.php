<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.officer')]
final class WithdrawalPayments extends Component
{
    use WithFileUploads;

    public string $recipientVerification = 'kartu_nasabah';

    public ?UploadedFile $proof = null;

    public string $recipientReference = '';

    public string $idempotencyKey = '';

    public ?int $selectedWithdrawalId = null;

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function select(int $withdrawalId): void
    {
        $this->selectedWithdrawalId = $withdrawalId;
    }

    public function pay(WithdrawalService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $withdrawal = WithdrawalRequest::query()->findOrFail($this->selectedWithdrawalId);
        $this->validate(['recipientVerification' => ['required', 'in:kartu_nasabah,nomor_nasabah'], 'recipientReference' => ['required', 'string', 'min:3', 'max:120'], 'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf']]);
        if (! isset($this->proof)) {
            $this->addError('proof', 'Bukti pembayaran wajib diunggah.');

            return;
        }
        $service->pay($actor, $withdrawal, $this->recipientVerification, $this->recipientReference, $this->proof, $this->idempotencyKey);
        session()->flash('success', 'Pembayaran tercatat dan saldo keluar dibuat.');
        $this->reset(['selectedWithdrawalId', 'recipientReference']);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.treasurer.withdrawal-payments', [
            'withdrawals' => app(WithdrawalService::class)->payableFor($actor)->latest()->get(),
        ]);
    }
}
