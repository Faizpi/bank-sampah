<?php

declare(strict_types=1);

namespace App\Domain\Programs\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Programs\Models\CollectionTarget;
use Illuminate\Database\Eloquent\Builder;

final readonly class TargetProgressService
{
    /** @return array{weight_kg: string, subject_count: int, deposit_count: int, plastic_weight_kg: string} */
    public function aggregate(CollectionTarget $target): array
    {
        $query = $this->scopedDeposits($target);
        $rows = $query->with('items')->get();
        $weight = 0;
        $plastic = 0;
        $subjects = [];
        foreach ($rows as $deposit) {
            $subjects[$deposit->customer_id] = true;
            foreach ($deposit->items as $item) {
                $itemWeight = (float) $item->weight_kg;
                $weight += $itemWeight;
                if ($item->wasteType?->is_plastic === true) {
                    $plastic += $itemWeight;
                }
            }
        }

        return ['weight_kg' => number_format($weight, 3, '.', ''), 'subject_count' => count($subjects), 'deposit_count' => $rows->count(), 'plastic_weight_kg' => number_format($plastic, 3, '.', '')];
    }

    public function progress(CollectionTarget $target): string
    {
        return $target->status->value === 'ditutup' && $target->closed_progress_kg !== null
            ? (string) $target->closed_progress_kg
            : $this->aggregate($target)['weight_kg'];
    }

    /** @return Builder<Deposit> */
    private function scopedDeposits(CollectionTarget $target): Builder
    {
        return Deposit::query()->with('items.wasteType')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->whereDate('occurred_at', '>=', $target->period_start)->whereDate('occurred_at', '<=', $target->period_end)->whereHas('items', function (Builder $items) use ($target): void {
            $scopes = $target->scopes;
            if ($scopes->isEmpty()) {
                return;
            }
            $items->where(function (Builder $query) use ($scopes): void {
                foreach ($scopes as $scope) {
                    $query->orWhere(function (Builder $match) use ($scope): void {
                        if ($scope->waste_type_id !== null) {
                            $match->where('waste_type_id', $scope->waste_type_id);
                        }
                        if ($scope->waste_category_id !== null) {
                            $match->whereHas('wasteType', static fn (Builder $type): Builder => $type->where('waste_category_id', $scope->waste_category_id));
                        }
                    });
                }
            });
        })->whereHas('customer.customerProfile', function (Builder $customer) use ($target): void {
            $rtIds = $target->scopes->pluck('rt_id')->filter()->values()->all();
            if ($rtIds !== []) {
                $customer->whereIn('rt_id', $rtIds);
            }
        });
    }
}
