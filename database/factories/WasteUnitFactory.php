<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\WasteMaster\Models\WasteUnit;

/** @extends WasteMasterFactory<WasteUnit> */
final class WasteUnitFactory extends WasteMasterFactory
{
    protected $model = WasteUnit::class;

    public function definition(): array
    {
        return [
            'code' => fake()->unique()->bothify('UNIT-####'),
            'name' => fake()->word(),
            'symbol' => fake()->lexify('???'),
            'classification' => WasteUnit::CLASSIFICATION_NON_WEIGHT,
            'conversion_factor_to_kg' => null,
        ];
    }

    public function weight(?string $conversionFactorToKg = null): static
    {
        return $this->state(fn (): array => [
            'classification' => WasteUnit::CLASSIFICATION_WEIGHT,
            'conversion_factor_to_kg' => $conversionFactorToKg,
        ]);
    }

    public function nonWeight(): static
    {
        return $this->state(fn (): array => [
            'classification' => WasteUnit::CLASSIFICATION_NON_WEIGHT,
            'conversion_factor_to_kg' => null,
        ]);
    }
}
