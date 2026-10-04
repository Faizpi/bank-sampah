<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

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
            $this->addError('proof', 'Bukti handover wajib diunggah.');

            return;
        }
        $completed = $service->handover($actor, $this->redemption((int) $this->selectedRedemptionId), $this->recipientVerification, $this->recipientReference, $this->proof, $this->idempotencyKey);
        $this->receipt = ['number' => $completed->request_number, 'value' => $completed->value_snapshot, 'occurredAt' => now('Asia/Jakarta')->translatedFormat('d F Y, H:i'), 'status' => $completed->status->value];
        session()->flash('success', 'Handover tercatat dan saldo warga berhasil dikurangi.');
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
