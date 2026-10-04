<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Services\CustomerQrPresenter;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class CustomerCard extends Component
{
    public string $customerName = '';

    public string $customerNumber = '';

    public string $maskedNumber = '';

    public string $serviceArea = '';

    public ?string $qrImageSrc = null;

    public bool $available = false;

    public function mount(PermissionChecker $permissions, CustomerQrPresenter $qrPresenter): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);

        $profile = $actor->customerProfile;
        if ($profile === null || $profile->customer_number === null || $actor->status->value !== 'aktif') {
            return;
        }

        $profile->loadMissing('rt.rw.dusun');

        $this->customerName = $actor->name;
        $this->customerNumber = $profile->customer_number;
        $this->maskedNumber = substr($this->customerNumber, 0, 4).'****'.substr($this->customerNumber, -2);
        $this->serviceArea = $profile->rt->name ?? 'Desa Sindangheula';
        $encryptedToken = $profile->qr_token_encrypted;
        if (is_string($encryptedToken) && $encryptedToken !== '') {
            $this->qrImageSrc = $qrPresenter->dataUri(QrToken::fromValue($encryptedToken));
        }
        $this->available = true;
    }

    public function render(): View
    {
        return view('livewire.citizen.customer-card');
    }
}
