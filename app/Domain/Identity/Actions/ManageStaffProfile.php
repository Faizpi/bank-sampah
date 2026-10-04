<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageStaffProfile
{
    public function __construct(
        private VisibleUsers $visibleUsers,
        private AuditLogger $auditLogger,
    ) {}

    /** @param array{service_area_id: int|string, active_from?: string|null, active_to?: string|null} $data */
    public function save(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('manageUpdate', $subject);
        $attributes = $this->profileAttributes($data);

        return DB::transaction(function () use ($actor, $subject, $attributes): User {
            $locked = $this->visibleUsers->queryFor($actor, ...\App\Domain\Identity\Enums\UserStatus::cases())
                ->whereKey($subject->getKey())
                ->with('roles')
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof User) {
                throw new AuthorizationException('Pengguna berada di luar scope Anda.');
            }

            if (! $locked->roles->contains(static fn (Role $role): bool => in_array($role->name, ['petugas', 'bendahara'], true))) {
                throw ValidationException::withMessages(['role' => 'Profil petugas hanya tersedia untuk pengguna dengan role petugas atau bendahara.']);
            }

            $profile = $locked->staffProfile()->lockForUpdate()->first();
            $old = $profile instanceof StaffProfile
                ? [
                    'service_area_id' => $profile->service_area_id,
                    'active_from' => $profile->active_from?->toDateString(),
                    'active_to' => $profile->active_to?->toDateString(),
                ]
                : [];

            $profile ??= new StaffProfile(['user_id' => $locked->id, 'staff_number' => $this->staffNumber($locked)]);
            $profile->forceFill($attributes)->save();

            $this->auditLogger->record(
                $actor,
                'identity.staff_profile.'.($old === [] ? 'created' : 'updated'),
                $locked,
                $old,
                ['staff_number' => $profile->staff_number, ...$attributes],
                $this->correlationId(),
            );

            return $locked->fresh(['staffProfile.serviceArea', 'customerProfile', 'roles']);
        });
    }

    /** @param array<string, mixed> $data
     *  @return array{service_area_id: int, active_from: string|null, active_to: string|null}
     */
    private function profileAttributes(array $data): array
    {
        $serviceAreaId = (int) ($data['service_area_id'] ?? 0);
        if ($serviceAreaId < 1 || ! ServiceArea::query()->whereKey($serviceAreaId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['service_area_id' => 'Area pelayanan harus aktif.']);
        }

        $activeFrom = $this->date($data['active_from'] ?? null, 'active_from');
        $activeTo = $this->date($data['active_to'] ?? null, 'active_to');
        if ($activeFrom !== null && $activeTo !== null && $activeTo->lessThan($activeFrom)) {
            throw ValidationException::withMessages(['active_to' => 'Tanggal akhir aktif tidak boleh lebih awal dari tanggal mulai aktif.']);
        }

        return [
            'service_area_id' => $serviceAreaId,
            'active_from' => $activeFrom?->toDateString(),
            'active_to' => $activeTo?->toDateString(),
        ];
    }

    private function date(mixed $value, string $field): ?CarbonImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return CarbonImmutable::parse((string) $value)->startOfDay();
        } catch (\Throwable) {
            throw ValidationException::withMessages([$field => 'Tanggal tidak valid.']);
        }
    }

    private function staffNumber(User $user): string
    {
        return 'STF-'.str_pad((string) $user->id, 8, '0', STR_PAD_LEFT);
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
