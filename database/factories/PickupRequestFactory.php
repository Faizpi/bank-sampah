<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<PickupRequest> */
final class PickupRequestFactory extends Factory
{
    protected $model = PickupRequest::class;

    public function definition(): array
    {
        $customer = User::factory()->create(['status' => UserStatus::Active]);

        return [
            'request_number' => 'PUP-'.$this->faker->unique()->numerify('########'),
            'customer_id' => $customer->id,
            'rt_id' => fn (): int => Rt::query()->create(['rw_id' => 1, 'code' => 'RT-'.$customer->id, 'name' => 'RT Pickup', 'is_active' => true])->id,
            'service_area_id' => fn (): int => ServiceArea::query()->create(['name' => 'Area Pickup '.$customer->id, 'is_active' => true])->id,
            'address' => 'Alamat pickup '.$customer->id,
            'selected_date' => today()->addDay(),
            'scheduled_date' => null,
            'estimated_weight_kg' => '5.000',
            'notes' => null,
            'status' => PickupStatus::PendingReview,
            'rejection_reason' => null,
            'cancellation_reason' => null,
            'assigned_staff_id' => null,
            'deposit_id' => null,
        ];
    }
}
