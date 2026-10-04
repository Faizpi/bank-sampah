<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call(RolesAndPermissionsSeeder::class);
        $this->call(InitialAdminSeeder::class);

        if (! app()->environment('production')) {
            $this->call(DeveloperUsersSeeder::class);
        }
    }
}
