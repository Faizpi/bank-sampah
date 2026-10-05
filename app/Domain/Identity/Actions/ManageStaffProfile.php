<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
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

    /** @param array{service_areas?: list<array{service_area_id: int|string, active_from?: string|null, active_to?: string|null}>, service_area_id?: int|string, active_from?: string|null, active_to?: string|null} $data */
    public function save(User $actor, User $subject, array $data): User
    {
        Gate::forUser($actor)->authorize('manageUpdate', $subject);
        $assignments = $this->assignmentAttributes($data);

        return DB::transaction(function () use ($actor, $subject, $assignments): User {
            $locked = $this->visibleUsers->queryFor($actor, ...UserStatus::cases())
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
            $profile ??= new StaffProfile(['user_id' => $locked->id, 'staff_number' => $this->staffNumber($locked)]);
            $profile->save();
            $old = $profile->serviceAreas()->lockForUpdate()->get()->map(fn ($assignment): array => [
                'service_area_id' => $assignment->service_area_id,
                'active_from' => $assignment->active_from?->toDateString(),
                'active_to' => $assignment->active_to?->toDateString(),
            ])->all();

            $profile->serviceAreas()->delete();
            $profile->serviceAreas()->createMany($assignments);
            $profile->forceFill([
                'service_area_id' => $assignments[0]['service_area_id'],
                'active_from' => $assignments[0]['active_from'],
                'active_to' => $assignments[0]['active_to'],
            ])->save();
            $this->auditLogger->record($actor, 'identity.staff_profile.'.($old === [] ? 'created' : 'updated'), $locked, ['assignments' => $old], ['staff_number' => $profile->staff_number, 'assignments' => $assignments], $this->correlationId());

            return $locked->fresh(['staffProfile.serviceAreas.serviceArea', 'customerProfile', 'roles']);
        });
    }

    /** @param array<string, mixed> $data
     * @return list<array{service_area_id: int, active_from: string|null, active_to: string|null}>
     */
    private function assignmentAttributes(array $data): array
    {
        $rows = $data['service_areas'] ?? null;
        $legacyPayload = ! is_array($rows);
        if ($legacyPayload) {
            $rows = isset($data['service_area_id']) ? [[
                'service_area_id' => $data['service_area_id'],
                'active_from' => $data['active_from'] ?? null,
                'active_to' => $data['active_to'] ?? null,
            ]] : [];
        }
        if ($rows === []) {
            throw ValidationException::withMessages(['service_areas' => 'Minimal satu area pelayanan wajib ditetapkan.']);
        }

        $assignments = [];
        $seen = [];
        foreach ($rows as $index => $row) {
            if (! is_array($row)) {
                throw ValidationException::withMessages(["service_areas.{$index}" => 'Penugasan area tidak valid.']);
            }
            $areaId = (int) ($row['service_area_id'] ?? 0);
            $areaField = $legacyPayload ? 'service_area_id' : "service_areas.{$index}.service_area_id";
            $fromField = $legacyPayload ? 'active_from' : "service_areas.{$index}.active_from";
            $toField = $legacyPayload ? 'active_to' : "service_areas.{$index}.active_to";
            if ($areaId < 1 || ! ServiceArea::query()->whereKey($areaId)->where('is_active', true)->exists()) {
                throw ValidationException::withMessages([$areaField => 'Area pelayanan harus aktif.']);
            }
            if (isset($seen[$areaId])) {
                throw ValidationException::withMessages([$areaField => 'Area pelayanan tidak boleh diulang.']);
            }
            $activeFrom = $this->date($row['active_from'] ?? null, $fromField);
            $activeTo = $this->date($row['active_to'] ?? null, $toField);
            if ($activeFrom !== null && $activeTo !== null && $activeTo->lessThan($activeFrom)) {
                throw ValidationException::withMessages([$toField => 'Tanggal akhir aktif tidak boleh lebih awal dari tanggal mulai aktif.']);
            }
            $seen[$areaId] = true;
            $assignments[] = ['service_area_id' => $areaId, 'active_from' => $activeFrom?->toDateString(), 'active_to' => $activeTo?->toDateString()];
        }

        return $assignments;
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
