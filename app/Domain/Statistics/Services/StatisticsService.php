<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Deposits\Models\Deposit;
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
    private const METRICS = ['active_customers', 'deposit_count', 'total_weight_kg', 'plastic_weight_kg', 'dominant_waste_type', 'target_progress_kg', 'mobile_service_count'];

    /** @var list<string> */
    private const DIMENSIONS = ['period', 'rt_id'];

    public function __construct(private PermissionChecker $permissions, private AuditLogger $auditLogger) {}

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
        }

        return $aggregate;
    }

    /** @return array<string, mixed> */
    public function public(string $start, string $end): array
    {
        $publication = StatisticPublication::query()->where('publication_key', 'public-dashboard')->where('is_active', true)->first();
        if ($publication === null) {
            return ['suppressed' => true, 'metrics' => []];
        }
        $aggregate = $this->aggregate($start, $end, null);
        if ($aggregate['subject_count'] < $publication->privacy_threshold) {
            return ['suppressed' => true, 'metrics' => []];
        }
        $rawMetrics = $publication->getAttribute('metrics');
        $configuredMetrics = is_array($rawMetrics) ? array_values(array_filter($rawMetrics, static fn (mixed $metric): bool => is_string($metric))) : [];
        $allowedMetrics = array_values(array_intersect($configuredMetrics, self::METRICS));
        $result = [];
        foreach ($allowedMetrics as $metric) {
            $result[$metric] = $aggregate[$metric] ?? null;
        }

        return ['suppressed' => false, 'metrics' => $result];
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
    private function aggregate(string $start, string $end, ?int $rtId): array
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', $end) !== 1 || $start >= $end) {
            throw ValidationException::withMessages(['period' => 'Periode statistik tidak valid.']);
        }
        $query = Deposit::query()->with('items.wasteType')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->whereDate('occurred_at', '>=', $start)->whereDate('occurred_at', '<', $end);
        if ($rtId !== null) {
            $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', $rtId));
        }
        $deposits = $query->get();
        $subjects = $deposits->pluck('customer_id')->unique()->count();
        $weight = 0.0;
        $plastic = 0.0;
        $types = [];
        foreach ($deposits as $deposit) {
            foreach ($deposit->items as $item) {
                $current = (float) $item->weight_kg;
                $weight += $current;
                $typeName = (string) ($item->wasteType->name ?? $item->waste_type_name ?? '');
                if ($typeName !== '') {
                    $types[$typeName] = ($types[$typeName] ?? 0.0) + $current;
                }
                if ($item->wasteType?->is_plastic === true) {
                    $plastic += $current;
                }
            }
        }
        arsort($types);

        return ['suppressed' => false, 'subject_count' => $subjects, 'deposit_count' => $deposits->count(), 'total_weight_kg' => number_format($weight, 3, '.', ''), 'plastic_weight_kg' => number_format($plastic, 3, '.', ''), 'dominant_waste_type' => array_key_first($types)];
    }

    private function canViewRegion(User $actor, int $rtId): bool
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return true;
        }

        return $actor->staffProfile?->serviceArea?->rts()->whereKey($rtId)->exists() ?? false;
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
