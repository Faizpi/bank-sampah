<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (DeveloperUsersSeeder::canSeedDemoData()) {
            $this->call(DeveloperUsersSeeder::class);
            $this->call(DemoMasterSeeder::class);

            return;
        }

        $this->call(InitialAdminSeeder::class);
    }
}
