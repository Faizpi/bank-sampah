<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;

/** @extends WasteMasterFactory<WasteType> */
final class WasteTypeFactory extends WasteMasterFactory
{
    protected $model = WasteType::class;

    public function definition(): array
    {
        return [
            'waste_category_id' => WasteCategory::factory(),
            'waste_unit_id' => WasteUnit::factory(),
            'code' => fake()->unique()->bothify('TYPE-####'),
            'name' => fake()->words(2, true),
            'education_description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 9999),
            'is_plastic' => fake()->boolean(),
            'media_id' => null,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
