<?php

declare(strict_types=1);

namespace App\Domain\Programs\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Shared\Weight;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class TargetService
{
    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger, private TargetProgressService $progress) {}

    /** @param list<array{waste_type_id?: int|null, waste_category_id?: int|null, rt_id?: int|null}> $scopes */
    public function create(User $actor, string $name, string $purpose, string $periodStart, string $periodEnd, string $targetWeightKg, bool $isPublic, array $scopes = []): CollectionTarget
    {
        $this->authorize($actor, 'target.manage');
        $scopes = $this->normalizeScopes($scopes);
        $data = $this->validatedData($name, $purpose, $periodStart, $periodEnd, $targetWeightKg, $isPublic, $scopes);

        return DB::transaction(function () use ($actor, $data, $scopes): CollectionTarget {
            $target = new CollectionTarget;
            $target->forceFill(array_merge($data, ['target_number' => 'TGT-'.strtoupper(Str::random(18)), 'status' => TargetStatus::Draft, 'created_by' => $actor->id]))->save();
            $this->syncScopes($target, $scopes);
            $this->auditLogger->record($actor, 'target.created', $target, [], $this->auditValues($target), $this->correlationId());

            return $target->fresh('scopes');
        });
    }

    /** @param list<array{waste_type_id?: int|null, waste_category_id?: int|null, rt_id?: int|null}> $scopes */
    public function update(User $actor, CollectionTarget $target, string $name, string $purpose, string $periodStart, string $periodEnd, string $targetWeightKg, bool $isPublic, array $scopes = []): CollectionTarget
    {
        $this->authorize($actor, 'target.manage');
        $scopes = $this->normalizeScopes($scopes);
        $data = $this->validatedData($name, $purpose, $periodStart, $periodEnd, $targetWeightKg, $isPublic, $scopes);

        return DB::transaction(function () use ($actor, $target, $data, $scopes): CollectionTarget {
            $locked = CollectionTarget::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== TargetStatus::Draft) {
                throw ValidationException::withMessages(['target' => 'Target aktif atau selesai tidak dapat diubah.']);
            }
            $old = $this->auditValues($locked);
            $locked->forceFill($data)->save();
            $this->syncScopes($locked, $scopes);
            $this->auditLogger->record($actor, 'target.updated', $locked, $old, $this->auditValues($locked), $this->correlationId());

            return $locked->fresh('scopes');
        });
    }

    public function activate(User $actor, CollectionTarget $target): CollectionTarget
    {
        $this->authorize($actor, 'target.publish');

        return DB::transaction(function () use ($actor, $target): CollectionTarget {
            $locked = CollectionTarget::query()->with('scopes')->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo(TargetStatus::Active)) {
                throw ValidationException::withMessages(['target' => 'Target tidak dapat diaktifkan dari status saat ini.']);
            }
            if ($this->hasAmbiguousActiveTarget($locked)) {
                throw ValidationException::withMessages(['target' => 'Target aktif lain dengan scope dan periode yang bertabrakan sudah ada.']);
            }
            $old = $locked->status->value;
            $locked->forceFill(['status' => TargetStatus::Active, 'published_by' => $actor->id])->save();
            $this->auditLogger->record($actor, 'target.activated', $locked, ['status' => $old], ['status' => TargetStatus::Active->value], $this->correlationId());

            return $locked->fresh('scopes');
        });
    }

    public function close(User $actor, CollectionTarget $target): CollectionTarget
    {
        $this->authorize($actor, 'target.manage');

        return DB::transaction(function () use ($actor, $target): CollectionTarget {
            $locked = CollectionTarget::query()->with('scopes')->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo(TargetStatus::Closed)) {
                throw ValidationException::withMessages(['target' => 'Target tidak dapat ditutup dari status saat ini.']);
            }
            $old = $locked->status->value;
            $locked->forceFill(['status' => TargetStatus::Closed, 'closed_at' => now(), 'closed_progress_kg' => $this->progress->progress($locked)])->save();
            $this->auditLogger->record($actor, 'target.closed', $locked, ['status' => $old], ['status' => TargetStatus::Closed->value, 'progress_kg' => $locked->closed_progress_kg], $this->correlationId());

            return $locked->fresh('scopes');
        });
    }

    public function cancel(User $actor, CollectionTarget $target, string $reason): CollectionTarget
    {
        $this->authorize($actor, 'target.manage');
        if (mb_strlen(trim($reason)) < 10 || mb_strlen(trim($reason)) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan pembatalan harus 10–1000 karakter.']);
        }

        return DB::transaction(function () use ($actor, $target, $reason): CollectionTarget {
            $locked = CollectionTarget::query()->whereKey($target->id)->lockForUpdate()->firstOrFail();
            if (! $locked->status->canTransitionTo(TargetStatus::Cancelled)) {
                throw ValidationException::withMessages(['target' => 'Target tidak dapat dibatalkan dari status saat ini.']);
            }
            $old = $locked->status->value;
            $locked->forceFill(['status' => TargetStatus::Cancelled])->save();
            $this->auditLogger->record($actor, 'target.cancelled', $locked, ['status' => $old], ['status' => TargetStatus::Cancelled->value, 'reason' => trim($reason)], $this->correlationId());

            return $locked;
        });
    }

    /** @return Builder<CollectionTarget> */
    public function visibleQuery(User $actor): Builder
    {
        if (! $this->permissions->allows($actor, 'target.view')) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap target.');
        }
        if ($this->permissions->allows($actor, 'target.publish') || $this->permissions->allows($actor, 'user.view.all')) {
            return CollectionTarget::query();
        }

        return CollectionTarget::query()->where('is_public', true);
    }

    /**
     * @param  list<array{waste_type_id?: int|null, waste_category_id?: int|null, rt_id?: int|null}>  $scopes
     * @return array{name: string, purpose: string, period_start: CarbonImmutable, period_end: CarbonImmutable, target_weight_kg: string, is_public: bool, public_min_subjects: int}
     */
    private function validatedData(string $name, string $purpose, string $periodStart, string $periodEnd, string $weight, bool $isPublic, array $scopes): array
    {
        $name = trim($name);
        $purpose = trim($purpose);
        if (mb_strlen($name) < 3 || mb_strlen($name) > 160 || mb_strlen($purpose) < 3 || mb_strlen($purpose) > 2000) {
            throw ValidationException::withMessages(['target' => 'Nama atau tujuan target tidak valid.']);
        }
        try {
            $start = CarbonImmutable::createFromFormat('!Y-m-d', $periodStart, 'Asia/Jakarta');
            $end = CarbonImmutable::createFromFormat('!Y-m-d', $periodEnd, 'Asia/Jakarta');
            if ($start === null || $end === null || $start >= $end) {
                throw new \RuntimeException;
            }
            $parsedWeight = Weight::fromDecimal($weight)->decimal();
        } catch (\Throwable) {
            throw ValidationException::withMessages(['period' => 'Periode atau berat target tidak valid.']);
        }
        if ($scopes === [] && ! $isPublic) {
            throw ValidationException::withMessages(['scopes' => 'Target internal harus memiliki scope.']);
        }
        foreach ($scopes as $scope) {
            $values = array_filter([$scope['waste_type_id'] ?? null, $scope['waste_category_id'] ?? null, $scope['rt_id'] ?? null], static fn (?int $value): bool => $value !== null);
            if (count($values) === 0) {
                throw ValidationException::withMessages(['scopes' => 'Scope target tidak valid.']);
            }
        }

        return ['name' => $name, 'purpose' => $purpose, 'period_start' => $start, 'period_end' => $end, 'target_weight_kg' => $parsedWeight, 'is_public' => $isPublic, 'public_min_subjects' => (int) config('app.statistics_privacy_threshold', 5)];
    }

    /** @param list<array{waste_type_id?: int|null, waste_category_id?: int|null, rt_id?: int|null}> $scopes */
    private function syncScopes(CollectionTarget $target, array $scopes): void
    {
        $target->scopes()->delete();
        foreach ($scopes as $scope) {
            $target->scopes()->create(['waste_type_id' => $scope['waste_type_id'] ?? null, 'waste_category_id' => $scope['waste_category_id'] ?? null, 'rt_id' => $scope['rt_id'] ?? null]);
        }
    }

    /**
     * @param  list<array<string, mixed>>  $scopes
     * @return list<array{waste_type_id: int|null, waste_category_id: int|null, rt_id: int|null}>
     */
    private function normalizeScopes(array $scopes): array
    {
        return array_map(static fn (array $scope): array => [
            'waste_type_id' => isset($scope['waste_type_id']) && $scope['waste_type_id'] !== '' ? (int) $scope['waste_type_id'] : null,
            'waste_category_id' => isset($scope['waste_category_id']) && $scope['waste_category_id'] !== '' ? (int) $scope['waste_category_id'] : null,
            'rt_id' => isset($scope['rt_id']) && $scope['rt_id'] !== '' ? (int) $scope['rt_id'] : null,
        ], $scopes);
    }

    private function hasAmbiguousActiveTarget(CollectionTarget $target): bool
    {
        return CollectionTarget::query()->with('scopes')->where('status', TargetStatus::Active)->where('id', '<>', $target->id)->where('period_start', '<', $target->period_end)->where(static fn (Builder $query): Builder => $query->where('period_end', '>', $target->period_start))->get()->contains(fn (CollectionTarget $other): bool => $this->scopesOverlap($target, $other));
    }

    private function scopesOverlap(CollectionTarget $first, CollectionTarget $second): bool
    {
        if ($first->scopes->isEmpty() || $second->scopes->isEmpty()) {
            return true;
        }

        return $first->scopes->contains(fn (TargetScope $left): bool => $second->scopes->contains(fn (TargetScope $right): bool => ($left->rt_id === null || $right->rt_id === null || $left->rt_id === $right->rt_id) && ($left->waste_type_id === null || $right->waste_type_id === null || $left->waste_type_id === $right->waste_type_id) && ($left->waste_category_id === null || $right->waste_category_id === null || $left->waste_category_id === $right->waste_category_id)));
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap target.');
        }
    }

    /** @return array<string, mixed> */
    private function auditValues(CollectionTarget $target): array
    {
        return ['target_number' => $target->target_number, 'status' => $target->status->value, 'period_start' => $target->period_start->toDateString(), 'period_end' => $target->period_end->toDateString(), 'target_weight_kg' => $target->target_weight_kg];
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
