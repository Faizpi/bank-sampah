<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Models\WasteCategory;

/** @extends WasteMasterFactory<WasteCategory> */
final class WasteCategoryFactory extends WasteMasterFactory
{
    protected $model = WasteCategory::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('CAT-####'),
            'name' => fake()->words(2, true),
            'sort_order' => fake()->numberBetween(0, 9999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
