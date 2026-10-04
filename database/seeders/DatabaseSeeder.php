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

        if (DeveloperUsersSeeder::canSeedDemoData()) {
            $this->call(DeveloperUsersSeeder::class);
            $this->call(LocalDataSeeder::class);
        }
    }
}
