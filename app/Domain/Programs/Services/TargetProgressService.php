<?php

declare(strict_types=1);

namespace App\Domain\Programs\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use Illuminate\Database\Eloquent\Builder;

final readonly class TargetProgressService
{
    /** @return array{weight_kg: string, subject_count: int, deposit_count: int, plastic_weight_kg: string} */
    public function aggregate(CollectionTarget $target): array
    {
        $cached = $this->cachedAggregates();
        if (isset($cached[$target->getKey()])) {
            return $cached[$target->getKey()];
        }

        $targets = CollectionTarget::query()->with('scopes')->get();

        $aggregates = $this->aggregateMany($targets);
        $this->cacheAggregates($aggregates);

        return $aggregates[$target->getKey()] ?? $this->aggregateMany([$target])[$target->getKey()];
    }

    /**
     * Aggregate a bounded target set from one deposit/item load rather than one full scan per target.
     *
     * @param  iterable<CollectionTarget>  $targets
     * @return array<int, array{weight_kg: string, subject_count: int, deposit_count: int, plastic_weight_kg: string}>
     */
    public function aggregateMany(iterable $targets): array
    {
        $targets = collect($targets)->values();
        if ($targets->isEmpty()) {
            return [];
        }

        /** @var array<int, array{weight: float, plastic: float, subjects: array<int, true>, deposits: int}> $stats */
        $stats = $targets->mapWithKeys(static fn (CollectionTarget $target): array => [
            $target->getKey() => ['weight' => 0.0, 'plastic' => 0.0, 'subjects' => [], 'deposits' => 0],
        ])->all();
        $scopes = $targets->mapWithKeys(static fn (CollectionTarget $target): array => [
            $target->getKey() => $target->relationLoaded('scopes') ? $target->scopes : $target->scopes()->get(),
        ]);
        $first = $targets->sortBy('period_start')->firstOrFail();
        $last = $targets->sortByDesc('period_end')->firstOrFail();

        $deposits = Deposit::query()
            ->with(['items.wasteType', 'customer.customerProfile'])
            ->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])
            ->whereDate('occurred_at', '>=', $first->period_start)
            ->whereDate('occurred_at', '<=', $last->period_end)
            ->where(function (Builder $query) use ($targets, $scopes): void {
                foreach ($targets as $target) {
                    $query->orWhere(function (Builder $match) use ($target, $scopes): void {
                        $match->whereDate('occurred_at', '>=', $target->period_start)
                            ->whereDate('occurred_at', '<=', $target->period_end);
                        $targetScopes = $scopes[$target->getKey()];
                        if ($targetScopes->isEmpty()) {
                            return;
                        }

                        $rtIds = $targetScopes->pluck('rt_id')->filter()->unique()->values()->all();
                        if ($rtIds !== []) {
                            $match->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->whereIn('rt_id', $rtIds));
                        }
                        $match->whereHas('items', static function (Builder $items) use ($targetScopes): void {
                            $items->where(function (Builder $itemQuery) use ($targetScopes): void {
                                foreach ($targetScopes as $scope) {
                                    $itemQuery->orWhere(function (Builder $scopeQuery) use ($scope): void {
                                        if ($scope->waste_type_id !== null) {
                                            $scopeQuery->where('waste_type_id', $scope->waste_type_id);
                                        }
                                        if ($scope->waste_category_id !== null) {
                                            $scopeQuery->whereHas('wasteType', static fn (Builder $type): Builder => $type->where('waste_category_id', $scope->waste_category_id));
                                        }
                                    });
                                }
                            });
                        });
                    });
                }
            })
            ->get();

        foreach ($deposits as $deposit) {
            foreach ($targets as $target) {
                if ($deposit->occurred_at->lt($target->period_start->startOfDay()) || $deposit->occurred_at->gt($target->period_end->endOfDay())) {
                    continue;
                }

                $targetScopes = $scopes[$target->getKey()];
                $rtIds = $targetScopes->pluck('rt_id')->filter()->unique()->values();
                if ($rtIds->isNotEmpty() && ! $rtIds->contains($deposit->customer?->customerProfile?->rt_id)) {
                    continue;
                }
                if ($targetScopes->isNotEmpty() && ! $deposit->items->contains(function ($item) use ($targetScopes): bool {
                    return $targetScopes->contains(static fn ($scope): bool => ($scope->waste_type_id === null || $scope->waste_type_id === $item->waste_type_id)
                        && ($scope->waste_category_id === null || $scope->waste_category_id === $item->wasteType?->waste_category_id));
                })) {
                    continue;
                }

                $stats[$target->getKey()]['subjects'][$deposit->customer_id] = true;
                $stats[$target->getKey()]['deposits']++;
                foreach ($deposit->items as $item) {
                    $weight = (float) $item->weight_kg;
                    $stats[$target->getKey()]['weight'] += $weight;
                    if ($item->wasteType?->is_plastic === true) {
                        $stats[$target->getKey()]['plastic'] += $weight;
                    }
                }
            }
        }

        return collect($stats)->map(static fn (array $stat): array => [
            'weight_kg' => number_format($stat['weight'], 3, '.', ''),
            'subject_count' => count($stat['subjects']),
            'deposit_count' => $stat['deposits'],
            'plastic_weight_kg' => number_format($stat['plastic'], 3, '.', ''),
        ])->all();
    }

    public function progress(CollectionTarget $target): string
    {
        return $target->status === TargetStatus::Closed && $target->closed_progress_kg !== null
            ? (string) $target->closed_progress_kg
            : $this->aggregate($target)['weight_kg'];
    }

    /** @return array<int, array{weight_kg: string, subject_count: int, deposit_count: int, plastic_weight_kg: string}> */
    private function cachedAggregates(): array
    {
        if (! app()->bound('request')) {
            return [];
        }

        $cached = request()->attributes->get('target_progress_aggregates');

        return is_array($cached) ? $cached : [];
    }

    /** @param array<int, array{weight_kg: string, subject_count: int, deposit_count: int, plastic_weight_kg: string}> $aggregates */
    private function cacheAggregates(array $aggregates): void
    {
        if (app()->bound('request')) {
            request()->attributes->set('target_progress_aggregates', $aggregates);
        }
    }
}
