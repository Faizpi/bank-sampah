<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

final class InitialAdminSeeder extends Seeder
{
    public function run(): void
    {
        $email = config('app.initial_admin_email');
        $password = config('app.initial_admin_password');

        if (! is_string($email) || $email === '' || ! is_string($password) || $password === '') {
            $this->command?->warn('InitialAdminSeeder skipped: set APP_INITIAL_ADMIN_EMAIL and APP_INITIAL_ADMIN_PASSWORD to bootstrap the first backoffice administrator.');

            return;
        }

        $admin = User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => 'Administrator Awal',
                'password' => Hash::make($password),
                'status' => UserStatus::Active,
                'verified_at' => now(),
                'terms_version' => (string) config('app.terms_version'),
                'terms_accepted_at' => now(),
            ],
        );

        $admin->roles()->syncWithoutDetaching(Role::query()->where('name', 'admin')->firstOrFail());
    }
}
