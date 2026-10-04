<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Contracts\CustomerNumber;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Shared\InvalidValue;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageCustomerIdentity
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
    ) {}

    /** @return array{number: CustomerNumber, token: QrToken} */
    public function issue(User $actor, User $customer): array
    {
        $this->authorize($actor, 'customer.card.issue');

        return DB::transaction(function () use ($actor, $customer): array {
            $customer = $this->lockVisibleCustomer($actor, $customer);
            $profile = $this->customerProfile($customer);

            if ($customer->status !== UserStatus::Active) {
                throw ValidationException::withMessages(['status' => 'Identitas hanya dapat diterbitkan untuk nasabah aktif.']);
            }

            if ($profile->customer_number !== null || $profile->qr_token_hash !== null) {
                throw ValidationException::withMessages(['identity' => 'Identitas nasabah sudah diterbitkan.']);
            }

            $identity = $this->persistIdentity($profile);
            $this->auditLogger->record($actor, 'identity.customer.card_issued', $customer, [], ['customer_number' => $identity['number']->value()], $this->correlationId());

            return $identity;
        });
    }

    /** @return array{number: CustomerNumber, token: QrToken} */
    public function rotateQr(User $actor, User $customer, string $reason = ''): array
    {
        $this->authorize($actor, 'customer.qr.rotate');
        $reason = trim($reason);
        $reason = $reason === '' ? 'QR dirotasi melalui pengelolaan identitas.' : $reason;

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan rotasi harus 10–1000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $customer, $reason): array {
            $customer = $this->lockVisibleCustomer($actor, $customer);
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
            $this->auditLogger->record($actor, 'identity.customer.qr_rotated', $customer, [], ['customer_number' => $profile->customerNumber()->value(), 'reason' => $reason], $this->correlationId());

            return ['number' => $profile->customerNumber(), 'token' => $token];
        });
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
            ->whereIn('user_id', $this->visibleUsers->queryFor($actor, UserStatus::Active)->select('users.id'))
            ->first();

        if ($profile === null || $profile->user === null) {
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

    private function lockVisibleCustomer(User $actor, User $subject): User
    {
        $customer = $this->visibleUsers->queryFor($actor, ...UserStatus::cases())
            ->whereKey($subject->getKey())
            ->with('customerProfile')
            ->lockForUpdate()
            ->first();

        if (! $customer instanceof User || ! $this->canAccessCustomer($actor, $customer)) {
            throw new AuthorizationException('Nasabah berada di luar scope Anda.');
        }

        return $customer;
    }

    private function canAccessCustomer(User $actor, User $customer): bool
    {
        return $this->visibleUsers->canView($actor, $customer, ...UserStatus::cases());
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
