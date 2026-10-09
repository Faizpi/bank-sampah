<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Support\Seeding\MasterData;
use Illuminate\Support\Facades\Hash;

final class MasterDataPayload
{
    /** @return array<string, mixed> */
    public static function make(): array
    {
        $tables = array_fill_keys(array_keys(MasterData::COLUMNS), []);
        $tables['dusun'] = [['id' => 1, 'code' => 'DESA-UJI', 'name' => 'Desa Uji', 'is_active' => 1]];
        $tables['rw'] = [['id' => 1, 'dusun_id' => 1, 'code' => 'RW001', 'name' => 'RW 001', 'is_active' => 1]];
        $tables['rt'] = [['id' => 1, 'rw_id' => 1, 'code' => 'RT001', 'name' => 'RT 001', 'is_active' => 1]];
        $tables['service_areas'] = [['id' => 1, 'name' => 'Area Uji', 'is_active' => 1]];
        $tables['service_area_rt'] = [['service_area_id' => 1, 'rt_id' => 1]];
        $hash = Hash::make('PrivateOnly#2026');
        foreach (['warga', 'petugas', 'bendahara', 'admin', 'admin'] as $index => $role) {
            $tables['users'][] = ['id' => $index + 1, 'username' => 'akun'.($index + 1), 'name' => 'Pengguna Uji '.($index + 1), 'password' => $hash, 'role' => $role, 'status' => 'aktif', 'terms_version' => 'v1.0', 'terms_accepted_at' => '2026-01-01 00:00:00'];
        }
        $tables['customer_profiles'] = [['user_id' => 1, 'customer_number' => 'CST-00000001', 'rt_id' => 1, 'address' => 'Jalan Uji nomor 1']];
        foreach ([2, 3] as $id) {
            $tables['staff_profiles'][] = ['user_id' => $id, 'staff_number' => 'STF-UJI-'.$id, 'service_area_id' => 1, 'active_from' => '2026-01-01', 'active_to' => null];
            $tables['staff_service_areas'][] = ['id' => $id, 'staff_profile_user_id' => $id, 'service_area_id' => 1, 'active_from' => '2026-01-01', 'active_to' => null];
        }
        $tables['waste_categories'] = [['id' => 1, 'code' => 'PLASTIK', 'name' => 'Plastik', 'sort_order' => 1, 'is_active' => 1]];
        $tables['waste_units'] = [['id' => 1, 'code' => 'KG', 'name' => 'Kilogram', 'symbol' => 'kg', 'classification' => 'weight', 'conversion_factor_to_kg' => '1.000000', 'is_active' => 1]];
        $tables['waste_conditions'] = [['id' => 1, 'code' => 'BERSIH', 'name' => 'Bersih', 'sort_order' => 1, 'is_active' => 1]];
        $tables['waste_types'] = [['id' => 1, 'waste_category_id' => 1, 'waste_unit_id' => 1, 'code' => 'BOTOL', 'name' => 'Botol Uji', 'sort_order' => 1, 'is_plastic' => 1, 'is_active' => 1]];
        $tables['waste_type_conditions'] = [['waste_type_id' => 1, 'waste_condition_id' => 1]];
        $tables['waste_prices'] = [['id' => 1, 'waste_type_id' => 1, 'waste_condition_id' => 1, 'price' => 1500, 'effective_from' => '2026-01-01 00:00:00', 'effective_to' => null, 'created_by' => 4, 'rounding_version' => 'half_up_v1']];

        return ['schema_version' => 1, 'tables' => $tables];
    }
}
