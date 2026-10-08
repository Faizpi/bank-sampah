<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
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
            'rt_id' => function () use ($customer): int {
                $rw = Rw::query()->first();
                if ($rw === null) {
                    $dusun = Dusun::query()->create(['code' => 'D-PUP-'.$customer->id, 'name' => 'Dusun Pickup', 'is_active' => true]);
                    $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-PUP-'.$customer->id, 'name' => 'RW Pickup', 'is_active' => true]);
                }

                return Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-'.$customer->id, 'name' => 'RT Pickup', 'is_active' => true])->id;
            },
            'service_area_id' => fn (): int => ServiceArea::query()->create(['name' => 'Area Pickup '.$customer->id, 'is_active' => true])->id,
            'address' => 'Alamat pickup '.$customer->id,
            'selected_date' => today()->addDay(),
            'scheduled_date' => null,
            'estimated_weight_kg' => '5.000',
            'notes' => null,
            'status' => PickupStatus::Waiting,
            'rejection_reason' => null,
            'cancellation_reason' => null,
            'assigned_staff_id' => null,
            'deposit_id' => null,
        ];
    }
}
