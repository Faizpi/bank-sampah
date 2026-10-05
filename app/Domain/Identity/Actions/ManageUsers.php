<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\DatabaseSession;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use App\Support\Auth\PhoneNumber;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
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

    /** @param array{name: string, phone: string, email?: string|null, password: string, password_confirmation: string, role_id: int|string} $data */
    public function create(User $actor, array $data): User
    {
        Gate::forUser($actor)->authorize('create', User::class);
        $attributes = $this->userAttributes($data);
        $password = $this->createPassword($data);
        $roleId = (int) $data['role_id'];
        $role = Role::query()->whereKey($roleId)->first();
        if (! $role instanceof Role) {
            throw ValidationException::withMessages(['role_id' => 'Peran yang dipilih tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $attributes, $password, $role): User {
            $user = new User;
            $user->forceFill([
                ...$attributes,
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
            ])->save();
            $user->roles()->attach($role->id, ['assigned_by' => $actor->id, 'reason' => 'Penugasan saat pembuatan pengguna.']);
            $this->auditLogger->record($actor, 'identity.user.created', $user, [], ['status' => UserStatus::Active->value, 'role_id' => $role->id], $this->correlationId());

            return $user->fresh('roles');
        });
    }

    /** @param array{name: string, phone: string, email?: string|null} $data */
    public function createCustomer(User $actor, array $data): User
    {
        if (! $this->permissions->allows($actor, 'customer.create-assisted') && ! $this->permissions->allows($actor, 'user.create')) {
            throw new AuthorizationException('Anda tidak memiliki akses membuat nasabah.');
        }

        $profile = $this->customerProfileAttributes($data);
        $attributes = $this->userAttributes($data);
        $this->ensureActiveRt((int) $profile['rt_id']);
        $this->ensureActorCanAccessCustomerRt($actor, (int) $profile['rt_id']);

        return DB::transaction(function () use ($actor, $attributes, $profile): User {
            $user = new User;
            $user->forceFill([
                ...$attributes,
                'password' => Hash::make(Str::random(64)),
                'status' => UserStatus::PendingVerification,
            ])->save();
            CustomerProfile::query()->create(['user_id' => $user->id, ...$profile]);
            $this->auditLogger->record($actor, 'identity.customer.created', $user, [], ['status' => UserStatus::PendingVerification->value, 'profile' => 'created'], $this->correlationId());

            return $user->fresh('customerProfile');
        });
    }

    /** @param array{name: string, phone: string, email?: string|null} $data */
    public function update(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('update', $subject);
        $attributes = $this->userAttributes($data);

        return DB::transaction(function () use ($actor, $attributes, $subject): User {
            $locked = $this->lockVisible($actor, $subject);
            $old = ['fields' => array_keys($attributes)];
            $locked->forceFill($attributes)->save();
            $this->auditLogger->record($actor, 'identity.user.updated', $locked, $old, ['fields' => array_keys($attributes)], $this->correlationId());

            return $locked->fresh();
        });
    }

    /** @param array{name: string, phone: string, email?: string|null, rt_id?: int, address?: string} $data */
    public function updateCustomer(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('updateCustomer', $subject);
        $attributes = $this->userAttributes($data);
        $profileAttributes = $this->customerProfileAttributes($data);
        $this->ensureActiveRt((int) $profileAttributes['rt_id']);
        $this->ensureActorCanAccessCustomerRt($actor, (int) $profileAttributes['rt_id']);

        return DB::transaction(function () use ($actor, $attributes, $profileAttributes, $subject): User {
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

        return DB::transaction(function () use ($actor, $subject): User {
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

        return DB::transaction(function () use ($actor, $subject, $reason): User {
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

    /** @return array{sessions_revoked: int, password_reset_tokens_revoked: int, role_assignments_revoked: int, operational_assignments_revoked: int} */
    private function revokeActiveAccess(User $user): array
    {
        $sessionsRevoked = DatabaseSession::query()->where('user_id', $user->id)->delete();
        $passwordResetTokensRevoked = $user->passwordResetTokens()->delete();
        $roleAssignmentsRevoked = $user->roles()->detach();

        $pickupAssignmentsRevoked = PickupRequest::query()
            ->where('assigned_staff_id', $user->id)
            ->whereIn('status', [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp])
            ->update(['assigned_staff_id' => null]);
        $withdrawalAssignmentsRevoked = WithdrawalRequest::query()
            ->where('payer_id', $user->id)
            ->where('status', WithdrawalStatus::ReadyForPickup)
            ->update(['payer_id' => null]);
        $groceryAssignmentsRevoked = GroceryRedemption::query()
            ->where('prepared_by_id', $user->id)
            ->whereIn('status', [GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])
            ->update(['prepared_by_id' => null]);
        $mobileAssignmentsRevoked = DB::table('mobile_service_staff')
            ->where('staff_id', $user->id)
            ->whereIn('mobile_service_id', MobileService::query()
                ->whereIn('status', [MobileServiceStatus::Draft, MobileServiceStatus::Published, MobileServiceStatus::Open])
                ->select('id'))
            ->delete();

        return [
            'sessions_revoked' => $sessionsRevoked,
            'password_reset_tokens_revoked' => $passwordResetTokensRevoked,
            'role_assignments_revoked' => $roleAssignmentsRevoked,
            'operational_assignments_revoked' => $pickupAssignmentsRevoked + $withdrawalAssignmentsRevoked + $groceryAssignmentsRevoked + $mobileAssignmentsRevoked,
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
     * @return array{name: string, phone: string, email?: string|null}
     */
    private function userAttributes(array $data): array
    {
        $name = trim((string) ($data['name'] ?? ''));
        $phone = PhoneNumber::normalize((string) ($data['phone'] ?? ''));
        $email = isset($data['email']) ? trim((string) $data['email']) : null;

        if (mb_strlen($name) < 2 || mb_strlen($name) > 120 || ! preg_match('/^62[0-9]{8,16}$/', $phone)) {
            throw ValidationException::withMessages(['user' => 'Nama atau nomor telepon pengguna tidak valid.']);
        }
        if ($email !== null && $email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw ValidationException::withMessages(['email' => 'Email pengguna tidak valid.']);
        }

        return ['name' => $name, 'phone' => $phone, 'email' => $email === '' ? null : $email];
    }

    /** @param array<string, mixed> $data */
    private function createPassword(array $data): string
    {
        $password = $data['password'] ?? null;
        $confirmation = $data['password_confirmation'] ?? null;
        if (! is_string($password) || mb_strlen($password) < 10 || $password !== $confirmation) {
            throw ValidationException::withMessages(['password' => 'Kata sandi minimal 10 karakter dan harus sama dengan konfirmasi.']);
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
