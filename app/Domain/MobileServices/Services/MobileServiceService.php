<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class MobileServiceService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

    /**
     * @param  list<int>  $staffIds
     * @param  list<int>  $wasteTypeIds
     */
    public function create(User $actor, ?int $rwId, ?int $rtId, string $point, string $startsAt, string $endsAt, int $capacity, string $notes, array $staffIds, array $wasteTypeIds): MobileService
    {
        $this->authorize($actor, 'mobile-service.manage');
        $data = $this->validated($rwId, $rtId, $point, $startsAt, $endsAt, $capacity, $notes, $staffIds, $wasteTypeIds);

        return DB::transaction(function () use ($actor, $data, $staffIds, $wasteTypeIds): MobileService {
            $service = new MobileService;
            $service->forceFill(array_merge($data, ['service_number' => 'MOB-'.strtoupper(Str::random(18)), 'status' => MobileServiceStatus::Draft, 'created_by' => $actor->id]))->save();
            $service->staff()->sync($staffIds);
            $service->wasteTypes()->sync($wasteTypeIds);
            $this->auditLogger->record($actor, 'mobile-service.created', $service, [], $this->auditValues($service), $this->correlationId());

            return $service->fresh(['staff', 'wasteTypes']);
        });
    }

    /**
     * @param  list<int>  $staffIds
     * @param  list<int>  $wasteTypeIds
     */
    public function update(User $actor, MobileService $service, ?int $rwId, ?int $rtId, string $point, string $startsAt, string $endsAt, int $capacity, string $notes, array $staffIds, array $wasteTypeIds): MobileService
    {
        $this->authorize($actor, 'mobile-service.manage');
        $data = $this->validated($rwId, $rtId, $point, $startsAt, $endsAt, $capacity, $notes, $staffIds, $wasteTypeIds);

        return DB::transaction(function () use ($actor, $service, $data, $staffIds, $wasteTypeIds): MobileService {
            $locked = MobileService::query()->with(['staff', 'wasteTypes'])->whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== MobileServiceStatus::Draft) {
                throw ValidationException::withMessages(['service' => 'Layanan yang sudah dipublikasikan tidak dapat diubah.']);
            }
            $old = $this->auditValues($locked);
            $locked->forceFill($data)->save();
            $locked->staff()->sync($staffIds);
            $locked->wasteTypes()->sync($wasteTypeIds);
            if ($this->hasCollision($locked->fresh(['staff']))) {
                throw ValidationException::withMessages(['schedule' => 'Jadwal layanan berbenturan pada petugas atau titik.']);
            }
            $this->auditLogger->record($actor, 'mobile-service.updated', $locked, $old, $this->auditValues($locked), $this->correlationId());

            return $locked->fresh(['staff', 'wasteTypes']);
        });
    }

    public function transition(User $actor, MobileService $service, MobileServiceStatus $next): MobileService
    {
        $this->authorize($actor, $next === MobileServiceStatus::Open || $next === MobileServiceStatus::Closed ? 'mobile-service.operate' : 'mobile-service.manage');

        return DB::transaction(function () use ($actor, $service, $next): MobileService {
            $locked = MobileService::query()->with(['staff', 'wasteTypes'])->whereKey($service->id)->lockForUpdate()->firstOrFail();
            $current = $locked->status;
            if (! $current->canTransitionTo($next)) {
                throw ValidationException::withMessages(['status' => 'Perubahan status layanan keliling tidak valid.']);
            }
            if (in_array($next, [MobileServiceStatus::Published, MobileServiceStatus::Open], true) && $this->hasCollision($locked)) {
                throw ValidationException::withMessages(['schedule' => 'Jadwal layanan berbenturan pada petugas atau titik.']);
            }
            if ($next === MobileServiceStatus::Open && ($locked->staff->isEmpty() || $locked->wasteTypes->isEmpty())) {
                throw ValidationException::withMessages(['service' => 'Layanan dibuka harus memiliki petugas dan jenis diterima.']);
            }
            $locked->forceFill(['status' => $next])->save();
            $this->auditLogger->record($actor, 'mobile-service.status.changed', $locked, ['status' => $current->value], ['status' => $next->value], $this->correlationId());

            return $locked;
        });
    }

    public function assignStaff(User $actor, MobileService $service, User $staff): MobileService
    {
        $this->authorize($actor, 'mobile-service.manage');
        if ($staff->status !== UserStatus::Active || ! $this->permissions->allows($staff, 'mobile-service.operate') || $staff->staffProfile === null) {
            throw ValidationException::withMessages(['staff_id' => 'Petugas tidak aktif atau tidak berwenang.']);
        }

        return DB::transaction(function () use ($actor, $service, $staff): MobileService {
            $locked = MobileService::query()->with('staff')->whereKey($service->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === MobileServiceStatus::Open || $locked->status === MobileServiceStatus::Closed || $locked->status === MobileServiceStatus::Cancelled) {
                throw ValidationException::withMessages(['service' => 'Penugasan tidak dapat diubah setelah layanan berjalan.']);
            }
            $locked->staff()->syncWithoutDetaching([$staff->id]);
            if ($this->hasCollision($locked->fresh(['staff']))) {
                throw ValidationException::withMessages(['staff_id' => 'Petugas sudah memiliki jadwal berbenturan.']);
            }
            $this->auditLogger->record($actor, 'mobile-service.staff.assigned', $locked, [], ['staff_id' => $staff->id], $this->correlationId());

            return $locked->fresh('staff');
        });
    }

    public function canOperate(User $actor, MobileService $service): bool
    {
        return $this->permissions->allows($actor, 'mobile-service.operate') && $service->staff()->whereKey($actor->id)->exists();
    }

    public function canAcceptDeposit(User $actor, MobileService $service, int $wasteTypeId): bool
    {
        return $service->isOpen() && $this->canOperate($actor, $service) && $service->wasteTypes()->whereKey($wasteTypeId)->exists() && $service->served_count < $service->capacity;
    }

    /** @return array{transaction_count: int, total_weight_kg: string, total_value: int} */
    public function recap(User $actor, MobileService $service): array
    {
        if (! $this->permissions->allows($actor, 'mobile-service.operate') || ! $this->canOperate($actor, $service)) {
            throw new AuthorizationException('Rekap layanan keliling berada di luar scope petugas.');
        }
        $deposits = Deposit::query()->with('correction')->where('mobile_service_id', $service->id)->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->get();

        return [
            'transaction_count' => $deposits->count(),
            'total_weight_kg' => number_format((float) $deposits->sum('total_weight_kg'), 3, '.', ''),
            'total_value' => $deposits->sum(static fn (Deposit $deposit): int => $deposit->effectiveTotalValue()),
        ];
    }

    /** @return Builder<MobileService> */
    public function publicQuery(): Builder
    {
        return MobileService::query()
            ->select(['id', 'service_number', 'rt_id', 'point', 'starts_at', 'ends_at', 'status', 'capacity', 'served_count', 'notes'])
            ->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])
            ->where('ends_at', '>=', now())
            ->orderBy('starts_at');
    }

    /**
     * @param  list<int>  $staffIds
     * @param  list<int>  $wasteTypeIds
     * @return array{rw_id: int|null, rt_id: int|null, point: string, starts_at: CarbonImmutable, ends_at: CarbonImmutable, capacity: int, notes: string|null}
     */
    private function validated(?int $rwId, ?int $rtId, string $point, string $startsAt, string $endsAt, int $capacity, string $notes, array $staffIds, array $wasteTypeIds): array
    {
        $point = trim($point);
        if (mb_strlen($point) < 3 || mb_strlen($point) > 255 || $capacity < 0 || $capacity > 1_000_000 || mb_strlen($notes) > 2000 || $staffIds === [] || $wasteTypeIds === []) {
            throw ValidationException::withMessages(['service' => 'Titik, kapasitas, petugas, atau jenis layanan tidak valid.']);
        }
        $start = CarbonImmutable::parse($startsAt, 'Asia/Jakarta');
        $end = CarbonImmutable::parse($endsAt, 'Asia/Jakarta');
        if ($start >= $end || ($rtId === null && $rwId === null)) {
            throw ValidationException::withMessages(['schedule' => 'Wilayah dan rentang waktu layanan tidak valid.']);
        }
        if ($rtId !== null && ! Rt::query()->whereKey($rtId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['rt_id' => 'RT layanan harus aktif.']);
        }
        if ($rwId !== null && ! Rw::query()->whereKey($rwId)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['rw_id' => 'RW layanan harus aktif.']);
        }
        if (User::query()->whereIn('id', $staffIds)->where('status', UserStatus::Active)->count() !== count(array_unique($staffIds)) || WasteType::query()->whereIn('id', $wasteTypeIds)->where('is_active', true)->count() !== count(array_unique($wasteTypeIds))) {
            throw ValidationException::withMessages(['assignment' => 'Petugas atau jenis sampah harus aktif.']);
        }

        return ['rw_id' => $rwId, 'rt_id' => $rtId, 'point' => $point, 'starts_at' => $start, 'ends_at' => $end, 'capacity' => $capacity, 'notes' => trim($notes) ?: null];
    }

    private function hasCollision(MobileService $service): bool
    {
        $staffIds = $service->staff->modelKeys();

        return MobileService::query()->with('staff')->where('id', '<>', $service->id)->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])->where('starts_at', '<', $service->ends_at)->where('ends_at', '>', $service->starts_at)->get()->contains(function (MobileService $other) use ($service, $staffIds): bool {
            $staffCollision = array_intersect($staffIds, $other->staff->modelKeys()) !== [];
            $pointCollision = $service->point === $other->point;

            return $staffCollision || $pointCollision;
        });
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap layanan keliling.');
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(MobileService $service): array
    {
        return ['service_number' => $service->service_number, 'status' => $service->status->value, 'starts_at' => $service->starts_at->toIso8601String(), 'ends_at' => $service->ends_at->toIso8601String(), 'capacity' => $service->capacity];
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
