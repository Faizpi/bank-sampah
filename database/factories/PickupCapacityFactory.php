<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Pickups\Models\PickupCapacity;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PickupCapacity> */
final class PickupCapacityFactory extends Factory
{
    protected $model = PickupCapacity::class;

    public function definition(): array
    {
        return [
            'service_area_id' => fn (): int => ServiceArea::query()->create(['name' => $this->faker->unique()->streetName(), 'is_active' => true])->id,
            'service_date' => today()->addDay(),
            'max_addresses' => 10,
            'max_weight_kg' => '100.000',
            'vehicle_label' => 'Kendaraan pickup',
            'is_active' => true,
        ];
    }
}
