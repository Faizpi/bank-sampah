<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Services\CustomerQrPresenter;
use App\Domain\Shared\InvalidValue;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class CustomerCard extends Component
{
    #[Locked]
    public string $customerName = '';

    #[Locked]
    public string $customerNumber = '';

    #[Locked]
    public string $maskedNumber = '';

    #[Locked]
    public string $serviceArea = '';

    #[Locked]
    public ?string $qrImageSrc = null;

    #[Locked]
    public bool $available = false;

    public function mount(PermissionChecker $permissions, CustomerQrPresenter $qrPresenter): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);

        $profile = $actor->customerProfile;
        if ($profile === null || $actor->status->value !== 'aktif') {
            return;
        }

        // Opening a card is read-only. Missing credentials are completed by an audited Admin action.
        if (! $profile->customer_number || ! $profile->qr_token_hash || ! $profile->qr_token_encrypted || $profile->joined_at === null) {
            return;
        }
        try {
            $token = QrToken::fromValue($profile->qr_token_encrypted);
            $number = $profile->customerNumber();
        } catch (InvalidValue) {
            return;
        }
        if (! hash_equals($profile->qr_token_hash, $token->hash())) {
            return;
        }

        $profile->loadMissing('rt.rw.dusun');

        $this->customerName = $actor->name;
        $this->customerNumber = $number->value();
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
