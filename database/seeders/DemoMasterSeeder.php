<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

final class DemoMasterSeeder extends Seeder
{
    public function run(): void
    {
        DeveloperUsersSeeder::requireDemoDataConfiguration();
        $this->seedWastePrices(User::where('username', 'admin')->firstOrFail());
        GroceryPackage::query()->firstOrCreate(['code' => 'DEMO-SEMBAKO'], [
            'name' => 'Paket Sembako Demo', 'contents' => 'Beras 2 kg dan minyak goreng 1 liter',
            'value' => 50000, 'status' => 'aktif',
        ]);
    }

    private function seedWastePrices(User $admin): void
    {
        WasteMasterMutationGuard::run(function () use ($admin): void {
            $plastic = WasteCategory::query()->updateOrCreate(['code' => 'PLASTIK'], ['name' => 'Plastik', 'sort_order' => 1, 'is_active' => true]);
            $paper = WasteCategory::query()->updateOrCreate(['code' => 'KERTAS'], ['name' => 'Kertas', 'sort_order' => 2, 'is_active' => true]);
            $unit = WasteUnit::query()->updateOrCreate(['code' => 'KG'], ['name' => 'Kilogram', 'symbol' => 'kg', 'classification' => WasteUnit::CLASSIFICATION_WEIGHT, 'conversion_factor_to_kg' => '1.000000', 'is_active' => true]);
            $mixed = WasteCondition::query()->updateOrCreate(['code' => 'CAMPURAN'], ['name' => 'Campuran', 'description' => 'Material campuran yang sudah kering.', 'sort_order' => 1, 'is_active' => true]);
            $clean = WasteCondition::query()->updateOrCreate(['code' => 'BERSIH'], ['name' => 'Bersih dan Kering', 'description' => 'Material bersih, kosong, dan kering.', 'sort_order' => 2, 'is_active' => true]);

            foreach ([
                ['code' => 'PLASTIK-CAMPURAN', 'name' => 'Plastik Campuran', 'category' => $plastic, 'condition' => $mixed, 'price' => 500],
                ['code' => 'AQUA-GELAS', 'name' => 'Aqua Gelas', 'category' => $plastic, 'condition' => $clean, 'price' => 1500],
                ['code' => 'AQUA-BOTOL', 'name' => 'Aqua Botol', 'category' => $plastic, 'condition' => $clean, 'price' => 1500],
                ['code' => 'KARDUS', 'name' => 'Kardus', 'category' => $paper, 'condition' => $clean, 'price' => 1500],
            ] as $index => $definition) {
                $type = WasteType::query()->updateOrCreate(
                    ['code' => $definition['code']],
                    ['waste_category_id' => $definition['category']->id, 'waste_unit_id' => $unit->id, 'name' => $definition['name'], 'education_description' => 'Diterima dalam kondisi '.$definition['condition']->name.'.', 'sort_order' => $index + 1, 'is_plastic' => $definition['category']->is($plastic), 'is_active' => true],
                );
                DB::table('waste_type_conditions')->upsert([['waste_type_id' => $type->id, 'waste_condition_id' => $definition['condition']->id]], ['waste_type_id', 'waste_condition_id']);
                WastePrice::query()->firstOrCreate(
                    ['waste_type_id' => $type->id, 'waste_condition_id' => $definition['condition']->id, 'effective_from' => '2026-01-01 00:00:00'],
                    ['price' => $definition['price'], 'effective_to' => null, 'created_by' => $admin->id, 'rounding_version' => 'half_up_v1'],
                );
            }
        });
    }
}
