<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\AssistedCustomerService as AssistedCustomerServiceAction;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.officer')]
final class CustomerIdentification extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    public string $search = '';

    #[Locked]
    public ?CustomerSummary $candidate = null;

    #[Locked]
    public bool $confirmed = false;

    public string $selectedService = '';

    public bool $scannerOpen = false;

    public bool $assistedConsent = false;

    public ?UploadedFile $assistedEvidence = null;

    public bool $assistedRecorded = false;

    public ?int $assistedServiceId = null;

    public ?int $mobileServiceId = null;

    public bool $withdrawalConsent = false;

    public ?UploadedFile $withdrawalEvidence = null;

    public string $withdrawalAmount = '';

    public string $withdrawalLocation = '';

    public string $withdrawalDate = '';

    #[Locked]
    public ?int $assistedWithdrawalServiceId = null;

    #[Locked]
    public ?int $assistedWithdrawalId = null;

    public string $assistedWithdrawalIdempotencyKey = '';

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);
        $this->withdrawalDate = today('Asia/Jakarta')->addDay()->toDateString();
        $this->assistedWithdrawalIdempotencyKey = (string) str()->uuid();
    }

    public function find(SearchCustomers $searchCustomers): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->resetCandidate();
        $this->validate(['search' => ['required', 'string', 'max:120']]);
        $matches = $searchCustomers->search($actor, $this->search, 1, $this->identificationMobileService($actor));
        $this->candidate = $matches[0] ?? null;
    }

    public function scan(string $rawToken, ManageCustomerIdentity $identity): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->resetCandidate();
        $this->scannerOpen = false;

        try {
            $this->candidate = $identity->scan($actor, $rawToken, $this->identificationMobileService($actor));
        } catch (Throwable $exception) {
            if ($exception instanceof ValidationException) {
                $this->scannerOpen = true;
                $this->addError('token', 'QR tidak ditemukan atau sudah tidak aktif.');

                return;
            }

            throw $exception;
        }
    }

    public function openScanner(): void
    {
        $this->resetErrorBag('token');
        $this->scannerOpen = true;
    }

    public function closeScanner(): void
    {
        $this->scannerOpen = false;
    }

    public function confirm(): void
    {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum konfirmasi nama.']);
        }

        $this->confirmed = true;
        $this->selectedService = 'deposit';
    }

    public function chooseService(string $service): void
    {
        if (! $this->confirmed || ! in_array($service, ['deposit', 'assisted', 'withdrawal'], true)) {
            throw ValidationException::withMessages(['search' => 'Konfirmasi nama warga sebelum memilih layanan.']);
        }

        $this->selectedService = $service;
    }

    public function updatedMobileServiceId(): void
    {
        $this->resetCandidate();
    }

    public function updatedWithdrawalAmount(): void
    {
        $this->resetValidation('withdrawalAmount');

        if ($this->candidate === null || ! ctype_digit($this->withdrawalAmount)) {
            return;
        }

        if ((int) $this->withdrawalAmount > $this->availableBalanceFor($this->candidate->userId)) {
            $this->addError('withdrawalAmount', 'Nominal melebihi saldo tersedia warga.');
        }
    }

    public function clearAssistedEvidence(): void
    {
        $this->clearMediaPickerUpload('assistedEvidence');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmAssistedEvidenceUpload(): array
    {
        return $this->confirmMediaPickerUpload(
            'assistedEvidence',
            ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            ['assistedEvidence.required' => 'Bukti persetujuan wajib diunggah dan hanya dapat dilihat oleh pihak berwenang.'],
        );
    }

    public function clearWithdrawalEvidence(): void
    {
        $this->clearMediaPickerUpload('withdrawalEvidence');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmWithdrawalEvidenceUpload(): array
    {
        return $this->confirmMediaPickerUpload(
            'withdrawalEvidence',
            ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            ['withdrawalEvidence.required' => 'Bukti persetujuan wajib diunggah dan hanya dapat dilihat oleh pihak berwenang.'],
        );
    }

    public function recordAssistedService(
        AssistedCustomerServiceAction $service,
        StorePrivateMedia $mediaStore,
    ): void {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum mencatat layanan berbantuan.']);
        }

        if (! $this->confirmed) {
            throw ValidationException::withMessages(['search' => 'Konfirmasi nama warga sebelum mencatat layanan berbantuan.']);
        }

        $this->validate([
            'assistedConsent' => ['accepted'],
            'assistedEvidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ], [
            'assistedConsent.accepted' => 'Persetujuan warga wajib dicatat.',
            'assistedEvidence.required' => 'Bukti persetujuan wajib diunggah dan hanya dapat dilihat oleh pihak berwenang.',
        ]);

        /** @var User $actor */
        $actor = auth()->user();
        /** @var UploadedFile $evidenceFile */
        $evidenceFile = $this->assistedEvidence;
        $media = $mediaStore->handleEvidence($evidenceFile, $actor);

        try {
            $record = $service->record(
                $actor,
                User::query()->findOrFail($this->candidate->userId),
                AssistedServiceContract::create(
                    $this->candidate->userId,
                    $actor->id,
                    'layanan_nasabah',
                    Consent::given('assisted-service-v1'),
                    EvidenceReference::privateMedia($media->id),
                ),
            );
            $this->assistedServiceId = $record->id;
        } catch (Throwable $exception) {
            Storage::disk($media->disk)->delete($media->path);
            $media->delete();

            throw $exception;
        }

        $this->assistedRecorded = true;
        $this->selectedService = 'assisted';
        session()->flash('success', 'Layanan berbantuan tercatat dengan persetujuan dan bukti yang hanya dapat dilihat oleh pihak berwenang.');
    }

    public function handoff(AssistedCustomerServiceAction $service): void
    {
        if ($this->assistedServiceId === null) {
            throw ValidationException::withMessages(['assistedEvidence' => 'Layanan berbantuan belum tercatat.']);
        }

        /** @var User $actor */
        $actor = auth()->user();
        $handoff = $service->handoff($actor, $this->assistedServiceId);
        session()->flash('assisted-handoff', $handoff);
    }

    public function requestAssistedWithdrawal(
        AssistedCustomerServiceAction $service,
        StorePrivateMedia $mediaStore,
        WithdrawalService $withdrawals,
    ): void {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum membuat pencairan berbantuan.']);
        }
        if (! $this->confirmed) {
            throw ValidationException::withMessages(['search' => 'Konfirmasi nama warga sebelum membuat pencairan berbantuan.']);
        }

        $this->validate([
            'withdrawalConsent' => ['accepted'],
            'withdrawalEvidence' => ['required_if:assistedWithdrawalServiceId,null', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            'withdrawalAmount' => ['required', 'integer', 'min:10000'],
            'withdrawalLocation' => ['required', 'string', 'min:3', 'max:255'],
            'withdrawalDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:today'],
        ], [
            'withdrawalConsent.accepted' => 'Persetujuan warga wajib dicatat.',
            'withdrawalEvidence.required_if' => 'Bukti persetujuan wajib diunggah dan hanya dapat dilihat oleh pihak berwenang.',
        ]);

        if ((int) $this->withdrawalAmount > $this->availableBalanceFor($this->candidate->userId)) {
            $this->addError('withdrawalAmount', 'Nominal melebihi saldo tersedia warga.');

            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        if ($this->assistedWithdrawalId !== null) {
            return;
        }

        if ($this->assistedWithdrawalServiceId === null) {
            /** @var UploadedFile $evidenceFile */
            $evidenceFile = $this->withdrawalEvidence;
            $media = $mediaStore->handleEvidence($evidenceFile, $actor);

            try {
                $record = $service->record(
                    $actor,
                    User::query()->findOrFail($this->candidate->userId),
                    AssistedServiceContract::create(
                        $this->candidate->userId,
                        $actor->id,
                        'pencairan',
                        Consent::given('assisted-withdrawal-v1'),
                        EvidenceReference::privateMedia($media->id),
                    ),
                );
                $this->assistedWithdrawalServiceId = $record->id;
            } catch (Throwable $exception) {
                Storage::disk($media->disk)->delete($media->path);
                $media->delete();

                throw $exception;
            }
        }

        try {
            $withdrawal = $withdrawals->request($actor, [
                'customer_id' => $this->candidate->userId,
                'amount' => $this->withdrawalAmount,
                'pickup_location' => $this->withdrawalLocation,
                'pickup_date' => $this->withdrawalDate,
                'assisted_service_id' => $this->assistedWithdrawalServiceId,
            ], $this->assistedWithdrawalIdempotencyKey);
        } catch (ValidationException $exception) {
            $this->presentAssistedWithdrawalErrors($exception);

            return;
        }

        $this->assistedWithdrawalId = $withdrawal->id;
        $this->selectedService = 'withdrawal';
        $this->withdrawalEvidence = null;
        session()->flash('success', 'Pencairan berbantuan berhasil diajukan dengan persetujuan dan bukti yang hanya dapat dilihat oleh pihak berwenang.');
    }

    public function render(PermissionChecker $permissions): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.officer.customer-identification', [
            'canCreateAssisted' => $permissions->allows($actor, 'customer.create-assisted'),
            'canCreateAssistedWithdrawal' => $permissions->allows($actor, 'customer.create-assisted') && $permissions->allows($actor, 'withdrawal.request'),
            'candidateAvailableBalance' => $this->candidate === null ? null : $this->availableBalanceFor($this->candidate->userId),
            'mobileServices' => $permissions->allows($actor, 'mobile-service.operate')
                ? MobileService::query()->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))->where('status', MobileServiceStatus::Open)->where('starts_at', '<=', now())->where('ends_at', '>=', now())->orderBy('starts_at')->get()
                : collect(),
        ]);
    }

    private function identificationMobileService(User $actor): ?MobileService
    {
        if ($this->mobileServiceId === null || ! app(PermissionChecker::class)->allows($actor, 'mobile-service.operate')) {
            return null;
        }

        return MobileService::query()
            ->whereKey($this->mobileServiceId)
            ->whereHas('staff', static fn (Builder $staff): Builder => $staff->whereKey($actor->id))
            ->where('status', MobileServiceStatus::Open)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now())
            ->first();
    }

    private function availableBalanceFor(int $customerId): int
    {
        return LedgerAccount::query()
            ->where('user_id', $customerId)
            ->first()
            ?->availableBalance() ?? 0;
    }

    private function presentAssistedWithdrawalErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $target = match ($field) {
                'amount', 'balance' => 'withdrawalAmount',
                'pickup_location' => 'withdrawalLocation',
                'pickup_date' => 'withdrawalDate',
                'assisted_service_id' => 'withdrawalEvidence',
                default => 'withdrawalAmount',
            };

            $this->addError($target, $messages[0] ?? 'Pencairan berbantuan tidak dapat diproses.');
        }
    }

    private function resetCandidate(): void
    {
        $this->clearAssistedEvidence();
        $this->clearWithdrawalEvidence();
        $this->reset(['candidate', 'confirmed', 'selectedService', 'assistedRecorded', 'assistedServiceId', 'assistedConsent', 'assistedEvidence', 'withdrawalConsent', 'withdrawalEvidence', 'withdrawalAmount', 'withdrawalLocation', 'assistedWithdrawalServiceId', 'assistedWithdrawalId']);
        $this->withdrawalDate = today('Asia/Jakarta')->addDay()->toDateString();
        $this->assistedWithdrawalIdempotencyKey = (string) str()->uuid();
        $this->resetErrorBag(['search', 'token', 'assistedConsent', 'assistedEvidence', 'withdrawalConsent', 'withdrawalEvidence', 'withdrawalAmount', 'withdrawalLocation', 'withdrawalDate']);
    }
}
