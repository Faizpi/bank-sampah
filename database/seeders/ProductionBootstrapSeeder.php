<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ProductionBootstrapSeeder extends Seeder
{
    public const INITIAL_PASSWORD = 'banten123';

    /** @var list<array{rt: string, kampung: string, rw: string}> */
    private const REGIONS = [
        ['rt' => '001', 'kampung' => 'Kepuh', 'rw' => '001'],
        ['rt' => '002', 'kampung' => 'Kepuh', 'rw' => '001'],
        ['rt' => '003', 'kampung' => 'Benoa Lor', 'rw' => '001'],
        ['rt' => '004', 'kampung' => 'Jalupang Kulon', 'rw' => '002'],
        ['rt' => '005', 'kampung' => 'Jalupang Wetan', 'rw' => '002'],
        ['rt' => '006', 'kampung' => 'Beluwen', 'rw' => '002'],
        ['rt' => '007', 'kampung' => 'Benoa Kidul', 'rw' => '002'],
        ['rt' => '008', 'kampung' => 'Pasagi Pasir', 'rw' => '003'],
        ['rt' => '009', 'kampung' => 'Ciwatu', 'rw' => '003'],
        ['rt' => '010', 'kampung' => 'Serut', 'rw' => '003'],
        ['rt' => '011', 'kampung' => 'Pasagi Kembang', 'rw' => '004'],
        ['rt' => '012', 'kampung' => 'Pasagi Kembang', 'rw' => '004'],
        ['rt' => '013', 'kampung' => 'Sindangheula', 'rw' => '004'],
        ['rt' => '014', 'kampung' => 'Pasir Kedung', 'rw' => '004'],
        ['rt' => '015', 'kampung' => 'Patanjungan', 'rw' => '005'],
        ['rt' => '016', 'kampung' => 'Paleuh', 'rw' => '005'],
        ['rt' => '017', 'kampung' => 'Suka Sari', 'rw' => '005'],
        ['rt' => '018', 'kampung' => 'Perumahan Pesona Sindangheula', 'rw' => '005'],
        ['rt' => '019', 'kampung' => 'Benoa Lor', 'rw' => '001'],
    ];

    /** @var list<array{name: string, username: string, rt: string, address?: string}> */
    private const RESIDENTS = [
        ['name' => 'APINAH', 'username' => 'apinah', 'rt' => '011'],
        ['name' => 'ALIMAH', 'username' => 'alimah', 'rt' => '003'],
        ['name' => 'NURPAIDA UDIROH PUTRI', 'username' => 'nurpaidaudirohputri', 'rt' => '011'],
        ['name' => 'OMAH', 'username' => 'omah', 'rt' => '015'],
        ['name' => 'META JUNIYANTI', 'username' => 'metajuniyanti', 'rt' => '015'],
        ['name' => 'NING WAHJU WIDI. R', 'username' => 'ningwahjuwidir', 'rt' => '015'],
        ['name' => 'ANISA', 'username' => 'anisa', 'rt' => '011'],
        ['name' => 'ABDUL JALIL', 'username' => 'abduljalil', 'rt' => '005', 'address' => 'KOMPLEK PURI SERANG HIJAU BLOK L7 NO.8'],
        ['name' => 'ROFEI', 'username' => 'rofei', 'rt' => '002'],
        ['name' => 'AGUS ALI JAFAR SODIK', 'username' => 'agusaliajafarsodik', 'rt' => '008'],
        ['name' => 'YUPI ZILINA', 'username' => 'yupizilina', 'rt' => '003', 'address' => 'JL. ANGKE INDAH'],
        ['name' => 'IRA FARIDA', 'username' => 'irafarida', 'rt' => '008'],
        ['name' => 'SUMIRAH', 'username' => 'sumirah', 'rt' => '017'],
        ['name' => 'MULYATI', 'username' => 'mulyati', 'rt' => '013'],
        ['name' => 'AYU RAHAYU', 'username' => 'ayurahayu', 'rt' => '011'],
        ['name' => 'MARTINI', 'username' => 'martini', 'rt' => '002'],
        ['name' => 'FUTIHATUR ROHMAH', 'username' => 'futihaturrohmah', 'rt' => '005'],
        ['name' => 'SUBHI', 'username' => 'subhi', 'rt' => '010'],
        ['name' => 'LILIS SUPARTI', 'username' => 'lilissuparti', 'rt' => '016'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(RolesAndPermissionsSeeder::class);
            $rts = $this->seedRegions();
            $users = $this->seedUsers($rts);
            $this->seedVillageServiceArea($rts, $users['petugas'], $users['bendahara']);
            $this->seedWastePrices($users['admin']);
        });

        $this->command?->info('Bootstrap production Sindangheula selesai: 1 desa, 5 RW, 19 wilayah RT/kampung, 19 warga, 4 akun pengelola, 1 area layanan, dan 4 harga sampah.');
    }

    /** @return array<string, Rt> */
    private function seedRegions(): array
    {
        return RegionMutationGuard::run(function (): array {
            $village = Dusun::query()->updateOrCreate(
                ['code' => 'DESA-SINDANGHEULA'],
                ['name' => 'Desa Sindangheula', 'is_active' => true],
            );

            $rws = [];
            foreach (range(1, 5) as $number) {
                $code = str_pad((string) $number, 3, '0', STR_PAD_LEFT);
                $rws[$code] = Rw::query()->updateOrCreate(
                    ['code' => "RW{$code}"],
                    ['dusun_id' => $village->id, 'name' => "RW {$code}", 'is_active' => true],
                );
            }

            $rts = [];
            foreach (self::REGIONS as $region) {
                $prefix = str_starts_with($region['kampung'], 'Perumahan') ? '' : 'Kp. ';
                $rts[$region['rt']] = Rt::query()->updateOrCreate(
                    ['code' => 'RT'.$region['rt']],
                    [
                        'rw_id' => $rws[$region['rw']]->id,
                        'name' => $prefix.$region['kampung'].' — RT '.$region['rt'],
                        'is_active' => true,
                    ],
                );
            }

            return $rts;
        });
    }

    /** @param array<string, Rt> $rts
     * @return array<string, User>
     */
    private function seedUsers(array $rts): array
    {
        $wargaRole = Role::query()->where('name', 'warga')->firstOrFail();
        foreach (self::RESIDENTS as $index => $resident) {
            $user = $this->upsertUser($resident['username'], $resident['name']);
            $user->roles()->syncWithoutDetaching([$wargaRole->id => ['assigned_by' => $user->id, 'reason' => 'Bootstrap production Sindangheula']]);
            $region = collect(self::REGIONS)->firstWhere('rt', $resident['rt']);
            CustomerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'customer_number' => 'SH-'.str_pad((string) ($index + 1), 5, '0', STR_PAD_LEFT),
                    'rt_id' => $rts[$resident['rt']]->id,
                    'address' => $resident['address'] ?? sprintf('%s, RT %s/RW %s, Desa Sindangheula', $region['kampung'], $resident['rt'], $region['rw']),
                    'joined_at' => now()->toDateString(),
                ],
            );
        }

        $staff = [];
        foreach (['petugas', 'bendahara', 'admin', 'superadmin'] as $roleName) {
            $user = $this->upsertUser($roleName, ucfirst($roleName));
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Bootstrap production Sindangheula']]);
            $staff[$roleName] = $user;
        }

        return $staff;
    }

    private function upsertUser(string $username, string $name): User
    {
        $user = User::withTrashed()->where('username', $username)->first() ?? new User;
        $user->forceFill([
            'username' => $username,
            'name' => $name,
            'phone' => null,
            'email' => null,
            'status' => UserStatus::Active,
            'verified_at' => $user->verified_at ?? now(),
            'terms_version' => $user->terms_version ?? (string) config('app.terms_version'),
            'terms_accepted_at' => $user->terms_accepted_at ?? now(),
            'deleted_at' => null,
        ]);
        if (! $user->exists) {
            $user->password = Hash::make(self::INITIAL_PASSWORD);
        }
        $user->save();

        return $user;
    }

    /** @param array<string, Rt> $rts */
    private function seedVillageServiceArea(array $rts, User $petugas, User $bendahara): void
    {
        $area = RegionMutationGuard::run(function () use ($rts): ServiceArea {
            $area = ServiceArea::query()->updateOrCreate(
                ['name' => 'Seluruh Desa Sindangheula'],
                ['is_active' => true],
            );
            $area->rts()->sync(array_map(static fn (Rt $rt): int => $rt->id, array_values($rts)));

            return $area;
        });

        foreach (['petugas' => $petugas, 'bendahara' => $bendahara] as $role => $user) {
            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['staff_number' => $role === 'petugas' ? 'STF-SH-001' : 'STF-SH-002', 'service_area_id' => $area->id, 'active_from' => now()->toDateString(), 'active_to' => null],
            );
            StaffServiceArea::query()->updateOrCreate(
                ['staff_profile_user_id' => $profile->user_id, 'service_area_id' => $area->id],
                ['active_from' => $profile->active_from, 'active_to' => null],
            );
        }
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
