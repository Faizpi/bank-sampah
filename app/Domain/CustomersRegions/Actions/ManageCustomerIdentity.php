<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\CustomerNumber;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Shared\InvalidValue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

final readonly class ManageCustomerIdentity
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @return array{number: CustomerNumber, token: QrToken} */
    public function issue(User $actor, User $customer): array
    {
        $this->authorize($actor, 'customer.card.issue');
        $profile = $this->customerProfile($customer);

        if (! $this->visibleUsers->canView($actor, $customer) && ! ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all'))) {
            throw new AuthorizationException('Nasabah berada di luar scope Anda.');
        }

        if ($customer->status !== UserStatus::Active) {
            throw ValidationException::withMessages(['status' => 'Identitas hanya dapat diterbitkan untuk nasabah aktif.']);
        }

        if ($profile->customer_number !== null || $profile->qr_token_hash !== null) {
            throw ValidationException::withMessages(['identity' => 'Identitas nasabah sudah diterbitkan.']);
        }

        return $this->persistIdentity($profile);
    }

    /** @return array{number: CustomerNumber, token: QrToken} */
    public function rotateQr(User $actor, User $customer): array
    {
        $this->authorize($actor, 'customer.qr.rotate');

        if (! $this->visibleUsers->canView($actor, $customer) && ! ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all'))) {
            throw new AuthorizationException('Nasabah berada di luar scope Anda.');
        }

        $profile = $this->customerProfile($customer);

        if ($customer->status !== UserStatus::Active || $profile->customer_number === null) {
            throw ValidationException::withMessages(['identity' => 'QR hanya dapat dirotasi untuk identitas nasabah aktif.']);
        }

        $token = QrToken::generate();
        $profile->forceFill([
            'qr_token_hash' => $token->hash(),
            'qr_token_encrypted' => $token->value(),
            'qr_rotated_at' => now(),
        ])->save();

        return ['number' => $profile->customerNumber(), 'token' => $token];
    }

    public function scan(User $actor, string $rawToken): CustomerSummary
    {
        $this->authorize($actor, 'customer.view');

        try {
            $token = QrToken::fromValue($rawToken);
        } catch (InvalidValue) {
            throw ValidationException::withMessages(['token' => 'QR tidak ditemukan atau sudah tidak aktif.']);
        }

        $profile = CustomerProfile::query()
            ->with('user')
            ->where('qr_token_hash', $token->hash())
            ->whereHas('user', static fn (Builder $user): Builder => $user->where('status', UserStatus::Active->value))
            ->first();

        if ($profile === null || $profile->user === null || ! $this->canAccessCustomer($actor, $profile->user)) {
            throw ValidationException::withMessages(['token' => 'QR tidak ditemukan atau sudah tidak aktif.']);
        }

        return new CustomerSummary($profile->user->getKey(), $profile->user->name, $profile->customerNumber());
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk identitas nasabah.');
        }
    }

    private function customerProfile(User $customer): CustomerProfile
    {
        $profile = $customer->customerProfile;

        if (! $profile instanceof CustomerProfile) {
            throw ValidationException::withMessages(['customer' => 'Profil nasabah tidak ditemukan.']);
        }

        return $profile;
    }

    /** @return array{number: CustomerNumber, token: QrToken} */
    private function persistIdentity(CustomerProfile $profile): array
    {
        $number = $this->newCustomerNumber();
        $token = QrToken::generate();
        $profile->forceFill([
            'customer_number' => $number->value(),
            'qr_token_hash' => $token->hash(),
            'qr_token_encrypted' => $token->value(),
            'qr_rotated_at' => now(),
            'joined_at' => today(),
        ])->save();

        return ['number' => $number, 'token' => $token];
    }

    private function newCustomerNumber(): CustomerNumber
    {
        do {
            $number = CustomerNumber::fromString('CST-'.str_pad((string) random_int(0, 99_999_999), 8, '0', STR_PAD_LEFT));
        } while (CustomerProfile::query()->where('customer_number', $number->value())->exists());

        return $number;
    }

    private function canAccessCustomer(User $actor, User $customer): bool
    {
        if ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return true;
        }

        if (! $this->permissions->allows($actor, 'user.view.area')) {
            return $actor->is($customer);
        }

        return $this->visibleUsers->canView($actor, $customer);
    }
}
