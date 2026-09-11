<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<CustomerProfile> */
final class CustomerProfileFactory extends Factory
{
    protected $model = CustomerProfile::class;

    public function definition(): array
    {
        // Every generated profile ships a complete QR token pair so the citizen
        // card can render immediately, mirroring the production identity flow.
        $token = QrToken::generate();

        return [
            'user_id' => User::factory(),
            'customer_number' => 'CST-'.fake()->unique()->numerify('########'),
            'rt_id' => fn (): int => $this->createRt()->id,
            'address' => fake()->streetAddress(),
            'joined_at' => fake()->dateTimeBetween('-2 years'),
            'qr_token_hash' => $token->hash(),
            'qr_token_encrypted' => $token->value(),
            'qr_rotated_at' => now(),
        ];
    }

    private function createRt(): Rt
    {
        $dusun = Dusun::query()->create(['code' => fake()->unique()->bothify('DS-####'), 'name' => fake()->streetName()]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => fake()->bothify('RW-##'), 'name' => fake()->streetName()]);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => fake()->bothify('RT-##'), 'name' => fake()->streetName()]);
    }
}
