<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Notification;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Notification> */
final class NotificationFactory extends Factory
{
    protected $model = Notification::class;

    public function definition(): array
    {
        return [
            'recipient_id' => User::factory(),
            'type' => 'account.status_changed',
            'title' => 'Status akun diperbarui',
            'body' => 'Status akun Anda telah diperbarui.',
            'reference' => 'USR-'.fake()->unique()->numerify('########'),
            'read_at' => null,
            'scheduled_at' => null,
            'dedupe_key' => Str::uuid()->toString(),
        ];
    }
}
