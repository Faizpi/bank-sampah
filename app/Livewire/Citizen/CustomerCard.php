<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Services\CustomerQrPresenter;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use Carbon\CarbonImmutable;
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
        if ($profile === null || $actor->status->value !== 'aktif') {
            return;
        }

        $needsSave = false;
        if ($profile->customer_number === null || $profile->customer_number === '') {
            do {
                $number = 'CST-'.str_pad((string) random_int(1, 99_999_999), 8, '0', STR_PAD_LEFT);
            } while (CustomerProfile::query()->where('customer_number', $number)->exists());
            $profile->customer_number = $number;
            $needsSave = true;
        } elseif (preg_match('/^SH-([0-9]+)$/', (string) $profile->customer_number, $matches)) {
            $profile->customer_number = 'CST-'.str_pad($matches[1], 8, '0', STR_PAD_LEFT);
            $needsSave = true;
        }

        if ($profile->qr_token_hash === null || $profile->qr_token_encrypted === null) {
            $token = QrToken::generate();
            $profile->qr_token_hash = $token->hash();
            $profile->qr_token_encrypted = $token->value();
            $profile->qr_rotated_at = CarbonImmutable::now();
            $needsSave = true;
        }

        if ($profile->joined_at === null) {
            $profile->joined_at = CarbonImmutable::today();
            $needsSave = true;
        }

        if ($needsSave) {
            $profile->save();
        }

        $profile->loadMissing('rt.rw.dusun');

        $this->customerName = $actor->name;
        $this->customerNumber = (string) $profile->customer_number;
        $this->maskedNumber = substr($this->customerNumber, 0, 4).'****'.substr($this->customerNumber, -2);
        $this->serviceArea = $profile->rt->name ?? 'Area Layanan';
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
