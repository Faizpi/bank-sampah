<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
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

    #[Locked]
    public ?int $mobileServiceId = null;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);
        $requestedMobileServiceId = request()->integer('mobileServiceId');
        $this->mobileServiceId = $requestedMobileServiceId > 0 ? $requestedMobileServiceId : null;
        if ($this->mobileServiceId !== null && ! $this->identificationMobileService($actor) instanceof MobileService) {
            $this->mobileServiceId = null;
            $this->addError('mobileServiceId', 'Layanan keliling tidak aktif atau tidak ditugaskan kepada Anda. Pilih konteks lain.');
        }
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
        if (! $this->confirmed || ! in_array($service, ['deposit'], true)) {
            throw ValidationException::withMessages(['search' => 'Konfirmasi nama warga sebelum memilih layanan.']);
        }

        $this->selectedService = $service;
    }

    public function updatedMobileServiceId(): void
    {
        $this->resetValidation('mobileServiceId');
        if ($this->mobileServiceId !== null) {
            /** @var User $actor */
            $actor = auth()->user();
            if (! $this->identificationMobileService($actor) instanceof MobileService) {
                $this->mobileServiceId = null;
                $this->addError('mobileServiceId', 'Layanan keliling tidak aktif atau tidak ditugaskan kepada Anda. Pilih konteks lain.');
            }
        }

        $this->resetCandidate();
    }

    public function render(PermissionChecker $permissions): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.officer.customer-identification', [
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

    private function resetCandidate(): void
    {
        $this->reset(['candidate', 'confirmed', 'selectedService']);
        $this->resetErrorBag(['search', 'token']);
    }
}
