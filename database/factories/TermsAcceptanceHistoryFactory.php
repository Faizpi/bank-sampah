<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Identity\Models\TermsAcceptanceHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TermsAcceptanceHistory> */
final class TermsAcceptanceHistoryFactory extends Factory
{
    protected $model = TermsAcceptanceHistory::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'accepted_version' => 'v1.0',
            'accepted_at' => now(),
        ];
    }
}
