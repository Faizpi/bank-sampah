<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WithdrawalRequest> */
final class WithdrawalRequestFactory extends Factory
{
    protected $model = WithdrawalRequest::class;

    /**
     * @return array{request_number: string, customer_id: int, requested_by_id: int, amount: int, status: WithdrawalStatus, balance_hold_id: null}
     */
    public function definition(): array
    {
        $customer = User::factory()->create();

        return [
            'request_number' => 'WDR-'.$this->faker->unique()->numerify('########'),
            'customer_id' => $customer->id,
            'requested_by_id' => $customer->id,
            'amount' => 10_000,
            'status' => WithdrawalStatus::PendingVerification,
            'balance_hold_id' => null,
        ];
    }
}
