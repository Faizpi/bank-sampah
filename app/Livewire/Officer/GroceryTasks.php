<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Ledger\Models\IdempotencyKey;
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
final class GroceryTasks extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    public ?int $selectedRedemptionId = null;

    public string $recipientVerification = 'kartu_nasabah';

    public string $recipientReference = '';

    public string $scanToken = '';

    public ?UploadedFile $proof = null;

    public string $idempotencyKey = '';

    public bool $scannerOpen = false;

    public bool $handoverReviewOpen = false;

    public ?string $resolvedCustomerName = null;

    /** @var array{number: string, value: int, occurredAt: string, status: string}|null */
    public ?array $receipt = null;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && ($permissions->allows($actor, 'grocery.prepare') || $permissions->allows($actor, 'grocery.handover')), 403);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function prepare(int $redemptionId, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->selectedRedemptionId = $redemptionId;
        $service->prepare($actor, $this->redemption($redemptionId));
        session()->flash('success', 'Paket mulai disiapkan.');
    }

    public function ready(int $redemptionId, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->selectedRedemptionId = $redemptionId;
        $service->ready($actor, $this->redemption($redemptionId));
        session()->flash('success', 'Paket ditandai siap diambil.');
    }

    public function select(int $redemptionId): void
    {
        $this->selectedRedemptionId = $redemptionId;
        $this->recipientReference = '';
        $this->scanToken = '';
        $this->proof = null;
        $this->scannerOpen = false;
        $this->handoverReviewOpen = false;
        $this->resolvedCustomerName = null;
        $this->idempotencyKey = (string) str()->uuid();
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
            ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            ['proof.required' => 'Unggah bukti serah-terima sebelum melanjutkan.'],
        );
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
            $redemption = $this->redemption((int) $this->selectedRedemptionId);
            if ($candidate->userId !== $redemption->customer_id) {
                $this->addError('recipientReference', 'Kartu tidak cocok dengan nasabah pada penukaran ini.');

                return;
            }
            $this->recipientReference = (string) $candidate->number;
            $this->resolvedCustomerName = $candidate->name;
        } catch (ValidationException) {
            $this->scannerOpen = true;
            $this->addError('recipientReference', 'QR tidak ditemukan, tidak aktif, atau di luar cakupan tugas Anda.');
        }
    }

    public function reviewHandover(): void
    {
        $this->validateHandoverFields();
        $this->handoverReviewOpen = true;
    }

    public function cancelHandoverReview(): void
    {
        $this->handoverReviewOpen = false;
    }

    public function handover(GroceryService $service): void
    {
        if (! $this->handoverReviewOpen) {
            $this->addError('recipientReference', 'Tinjau lalu konfirmasi serah-terima sebelum melanjutkan.');

            return;
        }
        /** @var User $actor */
        $actor = auth()->user();
        $this->validateHandoverFields();
        if (! isset($this->proof)) {
            $this->addError('proof', 'Bukti serah-terima wajib diunggah.');

            return;
        }
        $redemptionId = (int) $this->selectedRedemptionId;
        $payloadHash = $this->handoverPayloadHash($redemptionId);

        try {
            $completed = $service->handover($actor, $this->redemption($redemptionId), $this->recipientVerification, $this->recipientReference, $this->proof, $this->idempotencyKey);
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            $completed = GroceryRedemption::query()->with(['proofMedia', 'receiptLedgerEntry'])->find($redemptionId);
            if (! $completed instanceof GroceryRedemption
                || $completed->status !== GroceryStatus::Completed
                || $completed->proof_media_id === null
                || $completed->receipt_ledger_entry_id === null
                || $completed->proofMedia === null
                || $completed->receiptLedgerEntry === null
                || ! $this->hasSucceededIdempotency($actor, 'grocery.handover', $this->idempotencyKey, $payloadHash, GroceryRedemption::class, $redemptionId)) {
                throw $exception;
            }
        }

        $this->completeHandoverSuccessState($completed);
    }

    private function handoverPayloadHash(int $redemptionId): ?string
    {
        if (! $this->proof instanceof UploadedFile) {
            return null;
        }
        $checksum = hash_file('sha256', $this->proof->getRealPath());
        if (! is_string($checksum)) {
            return null;
        }
        $payload = [
            'redemption_id' => $redemptionId,
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

    private function completeHandoverSuccessState(GroceryRedemption $completed): void
    {
        $this->receipt = $completed->handed_over_at === null
            ? null
            : ['number' => (string) $completed->request_number, 'value' => (int) $completed->value_snapshot, 'occurredAt' => $completed->handed_over_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i'), 'status' => $completed->status->value];
        session()->flash('success', 'Serah-terima tercatat dan saldo warga telah digunakan.');
        $this->reset(['selectedRedemptionId', 'recipientReference', 'proof', 'scannerOpen', 'handoverReviewOpen', 'resolvedCustomerName']);
        $this->idempotencyKey = (string) str()->uuid();
    }

    private function validateHandoverFields(): void
    {
        $this->validate([
            'recipientVerification' => ['required', 'in:kartu_nasabah,nomor_nasabah'],
            'recipientReference' => ['required', 'string', 'regex:/^CST-[0-9]{8}$/'],
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'recipientReference.required' => 'Masukkan nomor nasabah yang diverifikasi.',
            'recipientReference.regex' => 'Nomor nasabah harus berformat CST-########.',
            'proof.required' => 'Unggah bukti serah-terima sebelum melanjutkan.',
        ]);
    }

    public function render(GroceryService $service, PermissionChecker $permissions): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $canPrepare = $permissions->allows($actor, 'grocery.prepare');
        $canHandover = $permissions->allows($actor, 'grocery.handover');
        $redemptions = $permissions->allows($actor, 'grocery.view')
            ? $service->visibleFor($actor)->whereIn('status', [GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])->latest()->get()
            : ($canHandover ? $service->readyForHandover($actor)->latest()->get() : collect());

        return view('livewire.officer.grocery-tasks', [
            'redemptions' => $redemptions,
            'canPrepare' => $canPrepare,
            'canHandover' => $canHandover,
        ]);
    }

    private function redemption(int $id): GroceryRedemption
    {
        return GroceryRedemption::query()->findOrFail($id);
    }
}
