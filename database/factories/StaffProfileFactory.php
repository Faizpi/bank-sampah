<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\StaffProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<StaffProfile> */
final class StaffProfileFactory extends Factory
{
    protected $model = StaffProfile::class;

    public function definition(): array
    {
        $activeFrom = fake()->dateTimeBetween('-2 years', 'now');

        return [
            'user_id' => User::factory(),
            'staff_number' => 'STF-'.fake()->unique()->numerify('########'),
            'service_area_id' => fn (): int => ServiceArea::query()->create(['name' => fake()->unique()->streetName()])->id,
            'active_from' => $activeFrom,
            'active_to' => null,
        ];
    }
}
