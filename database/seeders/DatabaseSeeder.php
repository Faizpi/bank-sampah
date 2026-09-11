<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        if (config('app.demo_mode') && DeveloperUsersSeeder::canSeedDemoData()) {
            $this->call(DeveloperUsersSeeder::class);
            $this->call(LocalDataSeeder::class);

            return;
        }

        $this->call(ProductionBootstrapSeeder::class);
    }
}
