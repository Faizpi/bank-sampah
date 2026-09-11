<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Models\WasteCondition;

/** @extends WasteMasterFactory<WasteCondition> */
final class WasteConditionFactory extends WasteMasterFactory
{
    protected $model = WasteCondition::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('COND-####'),
            'name' => fake()->words(2, true),
            'description' => fake()->optional()->sentence(),
            'sort_order' => fake()->numberBetween(0, 9999),
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }
}
