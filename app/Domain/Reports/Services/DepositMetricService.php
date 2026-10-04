<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Domain\Deposits\Models\Deposit;
use Illuminate\Database\Eloquent\Collection;

final readonly class DepositMetricService
{
    /**
     * @param  Collection<int, Deposit>  $deposits
     * @return array{subject_count: int, deposit_count: int, total_weight_kg: string, total_value: int, plastic_weight_kg: string}
     */
    public function calculate(Collection $deposits): array
    {
        $subjects = $deposits->pluck('customer_id')->unique()->count();
        $weight = 0.0;
        $value = 0;
        $plastic = 0.0;
        foreach ($deposits as $deposit) {
            $value += (int) $deposit->total_value;
            foreach ($deposit->items as $item) {
                $itemWeight = (float) $item->weight_kg;
                $weight += $itemWeight;
                if ($item->wasteType?->is_plastic === true) {
                    $plastic += $itemWeight;
                }
            }
        }

        return ['subject_count' => $subjects, 'deposit_count' => $deposits->count(), 'total_weight_kg' => number_format($weight, 3, '.', ''), 'total_value' => $value, 'plastic_weight_kg' => number_format($plastic, 3, '.', '')];
    }
}
