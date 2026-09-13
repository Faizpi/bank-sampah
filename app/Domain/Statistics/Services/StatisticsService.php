<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class StatisticsService
{
    /** @var list<string> */
    private const METRICS = ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'dominant_waste_type', 'target_progress_kg'];

    /** @var list<string> */
    private const DIMENSIONS = ['period', 'rt_id'];

    /** Deposits stream through this many rows per database round trip so large periods stay bounded in memory. */
    private const STREAM_CHUNK_SIZE = 200;

    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
        private TargetProgressService $targetProgress,
        private VisibleUsers $visibleUsers,
    ) {}

    /** @return array<string, mixed> */
    public function internal(User $actor, string $start, string $end, ?int $rtId = null): array
    {
        $this->authorize($actor, 'statistics.internal.view');
        if ($rtId !== null && ! $this->canViewRegion($actor, $rtId)) {
            throw new AuthorizationException('Wilayah statistik berada di luar scope Anda.');
        }
        $aggregate = $this->aggregate($start, $end, $rtId);
        $threshold = (int) config('app.statistics_privacy_threshold', 5);
        if ($aggregate['subject_count'] < $threshold) {
            $aggregate['suppressed'] = true;
            $aggregate['subject_count'] = null;
            $aggregate['deposit_count'] = null;
            $aggregate['total_weight_kg'] = null;
            $aggregate['plastic_weight_kg'] = null;
            $aggregate['dominant_waste_type'] = null;
            $aggregate['active_customers'] = null;
            $aggregate['target_progress_kg'] = null;
        }

        return $aggregate;
    }

    /** @return array<string, mixed> */
    public function public(string $start, string $end, ?int $rtId = null): array
    {
        $period = ['start' => $start, 'end' => $end];
        $publication = StatisticPublication::query()->where('publication_key', 'public-dashboard')->where('is_active', true)->first();
        if ($publication === null) {
            return ['suppressed' => true, 'metrics' => [], 'period' => $period, 'rt_id' => null];
        }
        $rawDimensions = $publication->getAttribute('dimensions');
        $configuredDimensions = is_array($rawDimensions) ? array_values(array_filter($rawDimensions, static fn (mixed $dimension): bool => is_string($dimension))) : [];
        $aggregateRtId = in_array('rt_id', $configuredDimensions, true) ? $rtId : null;
        $aggregate = $this->aggregate($start, $end, $aggregateRtId, true);
        if ($aggregate['subject_count'] < $publication->privacy_threshold) {
            return ['suppressed' => true, 'metrics' => [], 'period' => $period, 'rt_id' => $aggregateRtId];
        }
        $rawMetrics = $publication->getAttribute('metrics');
        $configuredMetrics = is_array($rawMetrics) ? array_values(array_filter($rawMetrics, static fn (mixed $metric): bool => is_string($metric))) : [];
        $allowedMetrics = array_values(array_intersect($configuredMetrics, self::METRICS));
        $result = [];
        foreach ($allowedMetrics as $metric) {
            $result[$metric] = $aggregate[$metric] ?? null;
        }

        return ['suppressed' => false, 'metrics' => $result, 'period' => $period, 'rt_id' => $aggregateRtId];
    }

    /**
     * @param  list<string>  $metrics
     * @param  list<string>  $dimensions
     */
    public function configurePublic(User $actor, array $metrics, array $dimensions, int $threshold, bool $active): StatisticPublication
    {
        if (! $this->permissions->allows($actor, 'statistics.public.manage')) {
            throw new AuthorizationException('Anda tidak memiliki akses publikasi statistik.');
        }
        if (array_diff($metrics, self::METRICS) !== [] || array_diff($dimensions, self::DIMENSIONS) !== [] || $threshold < 2 || $threshold > 1000) {
            throw ValidationException::withMessages(['publication' => 'Metrik, dimensi, atau ambang statistik tidak diizinkan.']);
        }

        return DB::transaction(function () use ($actor, $metrics, $dimensions, $threshold, $active): StatisticPublication {
            $publication = StatisticPublication::query()->firstOrNew(['publication_key' => 'public-dashboard']);
            $old = $publication->exists ? ['metrics' => $publication->metrics, 'dimensions' => $publication->dimensions, 'privacy_threshold' => $publication->privacy_threshold, 'is_active' => $publication->is_active] : [];
            $publication->forceFill(['metrics' => array_values(array_unique($metrics)), 'dimensions' => array_values(array_unique($dimensions)), 'privacy_threshold' => $threshold, 'is_active' => $active, 'approved_by' => $actor->id, 'approved_at' => now()])->save();
            $this->auditLogger->record($actor, 'statistics.publication.configured', $publication, $old, ['metrics' => $publication->metrics, 'dimensions' => $publication->dimensions, 'privacy_threshold' => $publication->privacy_threshold, 'is_active' => $publication->is_active], $this->correlationId());

            return $publication;
        });
    }

    /** @return array<string, mixed> */
    private function aggregate(string $start, string $end, ?int $rtId, bool $publicOnly = false): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1 || $start >= $end) {
            throw ValidationException::withMessages(['period' => 'Periode statistik tidak valid.']);
        }
        $query = Deposit::query()->with('items.wasteType')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->whereDate('occurred_at', '>=', $start)->whereDate('occurred_at', '<', $end);
        if ($rtId !== null) {
            $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId));
        }
        // Counts and distinct subjects come from SQL so only the item stream is hydrated in bounded chunks.
        $depositCount = (clone $query)->count();
        $subjectCount = (clone $query)->distinct()->count('customer_id');
        $weightGrams = 0;
        $plasticGrams = 0;
        $typeGrams = [];
        foreach ($query->lazyById(self::STREAM_CHUNK_SIZE) as $deposit) {
            foreach ($deposit->items as $item) {
                // Weights are persisted with at most three decimals; scaled integers avoid float drift while matching the 3-decimal output.
                $grams = (int) round((float) $item->weight_kg * 1000);
                $weightGrams += $grams;
                $typeName = (string) ($item->wasteType->name ?? $item->waste_type_name ?? '');
                if ($typeName !== '') {
                    $typeGrams[$typeName] = ($typeGrams[$typeName] ?? 0) + $grams;
                }
                if ($item->wasteType?->is_plastic === true) {
                    $plasticGrams += $grams;
                }
            }
        }
        arsort($typeGrams);

        $activeCustomers = User::query()
            ->where('status', UserStatus::Active)
            ->whereHas('customerProfile')
            ->when($rtId !== null, static fn (Builder $customers): Builder => $customers->whereHas('customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId)))
            ->count();
        $targetProgress = $this->targetProgress($start, $end, $rtId, $publicOnly);

        return [
            'suppressed' => false,
            'subject_count' => $subjectCount,
            'active_customers' => $activeCustomers,
            'deposit_count' => $depositCount,
            'total_weight_kg' => $this->formatGrams($weightGrams),
            'plastic_weight_kg' => $this->formatGrams($plasticGrams),
            'dominant_waste_type' => array_key_first($typeGrams) ?? 'Tidak teridentifikasi',
            'target_progress_kg' => number_format($targetProgress, 3, '.', ''),
        ];
    }

    private function targetProgress(string $start, string $end, ?int $rtId, bool $publicOnly): float
    {
        $targets = CollectionTarget::query()
            ->with('scopes')
            ->whereIn('status', [TargetStatus::Active, TargetStatus::Closed])
            ->whereDate('period_start', '<', $end)
            ->whereDate('period_end', '>=', $start)
            ->when($publicOnly, static fn (Builder $query): Builder => $query->where('is_public', true))
            ->get();

        return (float) $targets->sum(function (CollectionTarget $target) use ($rtId): float {
            if ($rtId === null) {
                return (float) $this->targetProgress->progress($target);
            }

            return (float) $this->targetProgress->progressForRtIds($target, [$rtId]);
        });
    }

    private function canViewRegion(User $actor, int $rtId): bool
    {
        return $this->visibleUsers->canAccessCustomerRt($actor, $rtId);
    }

    private function formatGrams(int $grams): string
    {
        $fraction = $grams % 1000;
        $whole = intdiv($grams, 1000);

        return $whole.'.'.str_pad((string) $fraction, 3, '0', STR_PAD_LEFT);
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission)) {
            throw new AuthorizationException('Anda tidak memiliki akses statistik internal.');
        }
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
