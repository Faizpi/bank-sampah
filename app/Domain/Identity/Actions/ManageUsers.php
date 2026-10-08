<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\DatabaseSession;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Models\User;
use App\Support\Auth\PhoneNumber;
use App\Support\Auth\Username;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageUsers
{
    public function __construct(
        private PermissionChecker $permissions,
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array{name: string, username: string, phone?: string|null, email?: string|null, password: string, password_confirmation: string, role: string} $data */
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);
        $attributes = $this->userAttributes($data);
        $password = $this->createPassword($data);
        $role = UserRole::tryFrom((string) $data['role']);
        if ($role === null || $role === UserRole::Citizen) {
            throw ValidationException::withMessages(['role' => 'Pilih Petugas, Bendahara, atau Admin. Warga mendaftar sendiri.']);
        }

        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $attributes, $password, $role, $data): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            Gate::forUser($actor)->authorize('create', User::class);
            $user = new User;
            $user->forceFill([
                ...$attributes,
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
                'role' => $role,
            ])->save();
            if (in_array($role, [UserRole::Officer, UserRole::Treasurer], true)) {
                app(ManageStaffProfile::class)->save($actor, $user, $data);
            }
            $this->auditLogger->record($actor, 'identity.user.created', $user, [], ['status' => UserStatus::Active->value, 'role' => $role->value], $this->correlationId());

            return $user->fresh();
        });
    }

    /** @param array{name: string, username: string, phone?: string|null, email?: string|null, rt_id?: int, address?: string} $data */

    /** @param array{name: string, username: string, phone?: string|null, email?: string|null} $data */
    public function update(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $attributes = $this->userAttributes($data);

        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $attributes, $subject): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->permissions->allows($actor, 'user.update')) {
                throw new AuthorizationException('Akses Admin tidak lagi aktif.');
            }
            $locked = $this->lockVisible($actor, $subject);
            $old = ['fields' => array_keys($attributes)];
            $locked->forceFill($attributes)->save();
            $this->auditLogger->record($actor, 'identity.user.updated', $locked, $old, ['fields' => array_keys($attributes)], $this->correlationId());

            return $locked->fresh();
        });
    }

    /** @param array{name: string, username: string, phone?: string|null, email?: string|null, rt_id?: int, address?: string} $data */
    public function updateCustomer(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('updateCustomer', $subject);
        $attributes = $this->userAttributes($data);
        $profileAttributes = $this->customerProfileAttributes($data);
        $this->ensureActiveRt((int) $profileAttributes['rt_id']);
        $this->ensureActorCanAccessCustomerRt($actor, (int) $profileAttributes['rt_id']);

        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $attributes, $profileAttributes, $subject): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->permissions->allows($actor, 'user.update')) {
                throw new AuthorizationException('Akses Admin tidak lagi aktif.');
            }
            $locked = $this->lockVisible($actor, $subject);
            $profile = $locked->customerProfile;

            if (! $profile instanceof CustomerProfile) {
                throw ValidationException::withMessages(['customer' => 'Profil nasabah tidak ditemukan.']);
            }

            $locked->forceFill($attributes)->save();
            $profile->forceFill($profileAttributes)->save();
            $this->auditLogger->record($actor, 'identity.customer.updated', $locked, ['fields' => array_keys($attributes)], ['fields' => [...array_keys($attributes), ...array_keys($profileAttributes)]], $this->correlationId());

            return $locked->fresh('customerProfile');
        });
    }

    public function activate(User $actor, User $subject): User
    {
        Gate::forUser($actor)->authorize('activate', $subject);

        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $subject): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->permissions->allows($actor, 'user.update')) {
                throw new AuthorizationException('Akses Admin tidak lagi aktif.');
            }
            $locked = $this->lockVisible($actor, $subject);

            if ($locked->status !== UserStatus::Inactive) {
                throw ValidationException::withMessages(['status' => 'Hanya pengguna nonaktif yang dapat diaktifkan kembali.']);
            }

            $old = $locked->status->value;
            $locked->forceFill(['status' => UserStatus::Active])->save();
            $this->auditLogger->record($actor, 'identity.user.activated', $locked, ['status' => $old], ['status' => UserStatus::Active->value], $this->correlationId());

            return $locked->fresh();
        });
    }

    public function deactivate(User $actor, User $subject, string $reason): User
    {
        Gate::forUser($actor)->authorize('deactivate', $subject);
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan penonaktifan harus 10–1000 karakter.']);
        }

        return app(AdminAvailabilityGuard::class)->run(function () use ($actor, $subject, $reason): User {
            $actor = User::query()->lockForUpdate()->findOrFail($actor->id);
            if (! $this->permissions->allows($actor, 'user.update')) {
                throw new AuthorizationException('Akses Admin tidak lagi aktif.');
            }
            $locked = $this->lockVisible($actor, $subject);
            if ($locked->status !== UserStatus::Active) {
                throw ValidationException::withMessages(['status' => 'Hanya pengguna aktif yang dapat dinonaktifkan.']);
            }

            $old = $locked->status->value;
            $locked->forceFill(['status' => UserStatus::Inactive])->save();
            $revoked = $this->revokeActiveAccess($locked);
            $this->auditLogger->record(
                $actor,
                'identity.user.deactivated',
                $locked,
                ['status' => $old],
                ['status' => UserStatus::Inactive->value, 'reason' => $reason, ...$revoked],
                $this->correlationId(),
            );

            return $locked->fresh();
        });
    }

    /** @return Builder<User> */
    private function visibleQuery(User $actor): Builder
    {
        return $this->visibleUsers->queryFor($actor, ...UserStatus::cases());
    }

    private function lockVisible(User $actor, User $subject): User
    {
        $locked = $this->visibleQuery($actor)->whereKey($subject->getKey())->lockForUpdate()->first();

        if (! $locked instanceof User) {
            throw new AuthorizationException('Pengguna berada di luar scope Anda.');
        }

        return $locked->load('customerProfile');
    }

    /** @return array{sessions_revoked: int, password_reset_tokens_revoked: int} */
    private function revokeActiveAccess(User $user): array
    {
        return [
            'sessions_revoked' => DatabaseSession::query()->where('user_id', $user->id)->delete(),
            'password_reset_tokens_revoked' => $user->passwordResetTokens()->delete(),
        ];
    }

    private function ensureActorCanAccessCustomerRt(User $actor, int $rtId): void
    {
        if (! $this->visibleUsers->canAccessCustomerRt($actor, $rtId)) {
            throw new AuthorizationException('RT nasabah berada di luar area pelayanan Anda.');
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{name: string, username: string, phone: string|null, email?: string|null}
     */
    private function userAttributes(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $username = Username::normalize((string) ($data['username'] ?? ''));
        $phone = isset($data['phone']) && is_string($data['phone']) && trim($data['phone']) !== ''
            ? PhoneNumber::normalize($data['phone'])
            : null;
        $email = isset($data['email']) ? trim((string) $data['email']) : null;

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120) {
            throw ValidationException::withMessages(['user' => 'Nama pengguna tidak valid.']);
        }
        if (! Username::isValid($username)) {
            throw ValidationException::withMessages(['user' => 'Username tidak valid. Gunakan huruf kecil, angka, titik, garis bawah, atau tanda hubung tanpa spasi.']);
        }
        if ($phone !== null && ! preg_match('/^62[0-9]{8,16}$/', $phone)) {
            throw ValidationException::withMessages(['user' => 'Nomor telepon pengguna tidak valid.']);
        }
        if ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Email pengguna tidak valid.']);
        }

        return ['name' => $name, 'username' => $username, 'phone' => $phone, 'email' => $email === '' ? null : $email];
    }

    /** @param array<string, mixed> $data */
    private function createPassword(array $data): string
    {
        $password = $data['password'] ?? null;
        $confirmation = $data['password_confirmation'] ?? null;
        if (! is_string($password) || mb_strlen($password) < 8 || $password !== $confirmation) {
            throw ValidationException::withMessages(['password' => 'Kata sandi minimal 8 karakter dan harus sama dengan konfirmasi.']);
        }

        return $password;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{rt_id: int, address: string}
     */
    private function customerProfileAttributes(array $data): array
    {
        $rtId = (int) ($data['rt_id'] ?? 0);
        $address = trim((string) ($data['address'] ?? ''));
        if ($rtId < 1 || mb_strlen($address) < 5 || mb_strlen($address) > 500) {
            throw ValidationException::withMessages(['customer' => 'Wilayah atau alamat nasabah tidak valid.']);
        }

        return ['rt_id' => $rtId, 'address' => $address];
    }

    private function ensureActiveRt(int $rtId): void
    {
        if (! Rt::query()->whereKey($rtId)->where('is_active', true)->whereHas('rw', static fn (Builder $rw): Builder => $rw->where('is_active', true)->whereHas('dusun', static fn (Builder $dusun): Builder => $dusun->where('is_active', true)))->exists()) {
            throw ValidationException::withMessages(['rt_id' => 'RT harus aktif dan berada dalam hierarki wilayah aktif.']);
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
