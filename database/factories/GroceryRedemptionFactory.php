<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<GroceryRedemption> */
final class GroceryRedemptionFactory extends Factory
{
    protected $model = GroceryRedemption::class;

    public function definition(): array
    {
        $package = GroceryPackage::query()->first() ?? GroceryPackage::query()->create([
            'code' => 'PAKET-FACTORY-'.$this->faker->unique()->numberBetween(1, 999999),
            'name' => 'Paket Sembako Factory',
            'contents' => 'Beras dan kebutuhan pokok',
            'value' => 50_000,
            'status' => 'aktif',
        ]);
        $customer = User::factory()->create();

        return [
            'request_number' => 'GRC-'.$this->faker->unique()->numberBetween(100000, 999999),
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'grocery_package_id' => $package->id,
            'value_snapshot' => $package->value,
            'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
            'source_type' => GrocerySource::Balance,
            'status' => GroceryStatus::PendingVerification,
        ];
    }
}
