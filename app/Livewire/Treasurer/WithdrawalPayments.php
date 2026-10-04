<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.officer')]
final class WithdrawalPayments extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    public string $recipientVerification = 'kartu_nasabah';

    public ?UploadedFile $proof = null;

    public string $recipientReference = '';

    public string $scanToken = '';

    public string $idempotencyKey = '';

    public ?int $selectedWithdrawalId = null;

    public bool $showPaymentReview = false;

    public bool $scannerOpen = false;

    public ?string $resolvedCustomerName = null;

    /** @var array{number: string, value: int, occurredAt: string, status: string}|null */
    public ?array $receipt = null;

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function select(int $withdrawalId): void
    {
        $this->selectedWithdrawalId = $withdrawalId;
        $this->showPaymentReview = false;
        $this->scannerOpen = false;
        $this->resolvedCustomerName = null;
        $this->reset(['recipientReference', 'scanToken', 'proof']);
        $this->recipientVerification = 'kartu_nasabah';
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function updatedRecipientVerification(): void
    {
        $this->scannerOpen = false;
        $this->resolvedCustomerName = null;
        $this->resetErrorBag('recipientReference');
    }

    public function openScanner(): void
    {
        $this->resetErrorBag('recipientReference');
        $this->scannerOpen = true;
    }

    public function closeScanner(): void
    {
        $this->scannerOpen = false;
    }

    public function scanCustomerCard(string $rawToken, ManageCustomerIdentity $identity): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->scannerOpen = false;

        try {
            $candidate = $identity->scan($actor, $rawToken);
            $withdrawal = WithdrawalRequest::query()->with('customer.customerProfile')->findOrFail($this->selectedWithdrawalId);
            if ($candidate->userId !== $withdrawal->customer_id) {
                $this->addError('recipientReference', 'Kartu tidak cocok dengan nasabah pada pencairan ini.');

                return;
            }

            $this->recipientReference = (string) $candidate->number;
            $this->resolvedCustomerName = $candidate->name;
        } catch (ValidationException) {
            $this->scannerOpen = true;
            $this->addError('recipientReference', 'QR tidak ditemukan, tidak aktif, atau di luar cakupan tugas Anda.');
        }
    }

    public function reviewPayment(): void
    {
        $this->validatePaymentFields();
        $this->showPaymentReview = true;
    }

    public function cancelPaymentReview(): void
    {
        $this->showPaymentReview = false;
    }

    public function clearProof(): void
    {
        $this->clearMediaPickerUpload('proof');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmProofUpload(): array
    {
        return $this->confirmMediaPickerUpload(
            'proof',
            ['required', 'file', 'max:1024', 'mimes:jpg,jpeg,png'],
            $this->proofMessages(),
        );
    }

    public function pay(WithdrawalService $service): void
    {
        if (! $this->showPaymentReview) {
            $this->addError('selectedWithdrawalId', 'Tinjau lalu konfirmasi pembayaran sebelum melanjutkan.');

            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        $withdrawal = WithdrawalRequest::query()->findOrFail($this->selectedWithdrawalId);
        $this->validatePaymentFields();
        $payloadHash = $this->paymentPayloadHash($withdrawal->id);
        try {
            $paid = $service->pay($actor, $withdrawal, $this->recipientVerification, $this->recipientReference, $this->proof, $this->idempotencyKey);
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            $paid = WithdrawalRequest::query()->with(['proofMedia', 'receiptLedgerEntry'])->find($withdrawal->id);
            if (! $paid instanceof WithdrawalRequest
                || $paid->status !== WithdrawalStatus::Paid
                || $paid->proof_media_id === null
                || $paid->receipt_ledger_entry_id === null
                || $paid->proofMedia === null
                || $paid->receiptLedgerEntry === null
                || ! $this->hasSucceededIdempotency($actor, 'withdrawal.pay', $this->idempotencyKey, $payloadHash, WithdrawalRequest::class, $withdrawal->id)) {
                throw $exception;
            }
        }

        $this->completePaymentSuccessState($paid);
    }

    private function paymentPayloadHash(int $withdrawalId): ?string
    {
        if (! $this->proof instanceof UploadedFile) {
            return null;
        }
        $checksum = hash_file('sha256', $this->proof->getRealPath());
        if (! is_string($checksum)) {
            return null;
        }
        $payload = [
            'withdrawal_id' => $withdrawalId,
            'verification' => strtolower(trim($this->recipientVerification)),
            'reference' => trim($this->recipientReference),
            'proof_checksum' => $checksum,
        ];
        ksort($payload);

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }

    private function hasSucceededIdempotency(User $actor, string $scope, string $key, ?string $payloadHash, string $resultType, int $resultId): bool
    {
        return $payloadHash !== null && IdempotencyKey::query()
            ->where('actor_id', $actor->id)
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('status', 'succeeded')
            ->where('payload_hash', $payloadHash)
            ->where('result_type', $resultType)
            ->where('result_id', $resultId)
            ->exists();
    }

    private function completePaymentSuccessState(WithdrawalRequest $paid): void
    {
        $this->receipt = $paid->paid_at === null
            ? null
            : ['number' => (string) $paid->request_number, 'value' => (int) $paid->amount, 'occurredAt' => $paid->paid_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i'), 'status' => $paid->status->value];
        session()->flash('success', 'Pembayaran tercatat dan saldo keluar dibuat.');
        $this->reset(['selectedWithdrawalId', 'recipientReference', 'proof', 'showPaymentReview', 'scannerOpen', 'resolvedCustomerName']);
        $this->recipientVerification = 'kartu_nasabah';
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        $withdrawals = app(WithdrawalService::class)->payableFor($actor)->latest()->get();
        $selectedWithdrawal = $withdrawals->firstWhere('id', $this->selectedWithdrawalId);
        $availableBalance = null;
        if ($selectedWithdrawal !== null) {
            $selectedWithdrawal->customer?->loadMissing('ledgerAccount');
            $availableBalance = $selectedWithdrawal->customer?->ledgerAccount?->availableBalance();
        }

        return view('livewire.treasurer.withdrawal-payments', compact('withdrawals', 'selectedWithdrawal', 'availableBalance'));
    }

    private function validatePaymentFields(): void
    {
        $this->validate([
            'selectedWithdrawalId' => ['required', 'integer', 'min:1'],
            'recipientVerification' => ['required', 'in:kartu_nasabah,nomor_nasabah'],
            'recipientReference' => ['required', 'string', 'regex:/^CST-[0-9]{8}$/'],
            'proof' => ['required', 'file', 'max:1024', 'mimes:jpg,jpeg,png'],
        ], [
            'recipientReference.required' => 'Masukkan nomor nasabah yang diverifikasi.',
            'recipientReference.regex' => 'Nomor nasabah harus berformat CST-########.',
            ...$this->proofMessages(),
        ]);
    }

    /** @return array<string, string> */
    private function proofMessages(): array
    {
        return [
            'proof.required' => 'Tambahkan satu foto bukti pembayaran melalui kamera atau galeri.',
            'proof.max' => 'Foto bukti pembayaran maksimal 1 MB.',
            'proof.mimes' => 'Foto bukti pembayaran harus berupa JPG, JPEG, atau PNG.',
        ];
    }
}
