<?php

namespace Database\Factories;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'phone' => '628'.fake()->unique()->numerify('##########'),
            'email' => null,
            'email_verified_at' => null,
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'status' => UserStatus::Active,
            'verified_at' => now(),
            'terms_version' => 'test-v1',
            'terms_accepted_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    public function pendingVerification(): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::PendingVerification,
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => null,
        ]);
    }

    public function rejected(string $reason = 'Data domisili tidak dapat diverifikasi'): static
    {
        return $this->state(fn (): array => [
            'status' => UserStatus::Rejected,
            'verified_at' => null,
            'verified_by' => null,
            'rejection_reason' => $reason,
        ]);
    }

    public function inactive(): static
    {
        return $this->state(fn (): array => ['status' => UserStatus::Inactive]);
    }

    public function withEmail(): static
    {
        return $this->state(fn (): array => [
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
        ]);
    }
}
