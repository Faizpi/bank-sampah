<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use App\Support\Auth\Username;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $username = config('app.initial_admin_username');
        $email = config('app.initial_admin_email');
        $password = config('app.initial_admin_password');

        if (! is_string($username) || ! Username::isValid($username) || ! is_string($password) || mb_strlen($password) < 12) {
            $this->command?->warn('InitialAdminSeeder skipped: set APP_INITIAL_ADMIN_USERNAME and APP_INITIAL_ADMIN_PASSWORD (12+ characters) to bootstrap the first backoffice administrator.');

            return;
        }

        $existing = User::query()->where('username', $username)->first();
        if ($existing !== null) {
            if (! $existing->hasRole('admin') || $existing->status !== UserStatus::Active) {
                throw new \LogicException('Bootstrap must not elevate or reactivate an existing account.');
            }

            return;
        }
        $admin = new User;
        $admin->forceFill(
            ['username' => $username, 'email' => $email,
                'name' => 'Administrator Awal',
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
                'role' => 'admin',
                'verified_at' => now(),
                'terms_version' => (string) config('app.terms_version'),
                'terms_accepted_at' => now(),
            ],
        )->save();

    }
}
