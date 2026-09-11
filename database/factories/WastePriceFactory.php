<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Support\Carbon;

/** @extends WasteMasterFactory<WastePrice> */
final class WastePriceFactory extends WasteMasterFactory
{
    protected $model = WastePrice::class;

    public function definition(): array
    {
        $from = Carbon::now()->startOfDay();

        return [
            'waste_type_id' => WasteType::factory(),
            'waste_condition_id' => WasteCondition::factory(),
            'price' => fake()->numberBetween(0, 100_000),
            'effective_from' => $from,
            'effective_to' => null,
            'created_by' => null,
            'rounding_version' => 'half_up_v1',
        ];
    }

    public function period(Carbon $from, ?Carbon $to = null): static
    {
        return $this->state(fn (): array => [
            'effective_from' => $from,
            'effective_to' => $to,
        ]);
    }
}
