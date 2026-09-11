<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\CustomersRegions\Contracts\QrToken;
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
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

final class ProductionBootstrapSeeder extends Seeder
{
    public const INITIAL_PASSWORD = 'Banten123';

    /** @var list<array{rt: string, kampung: string, rw: string}> */
    private const REGIONS = [
        ['rt' => '001', 'kampung' => 'Sukamaju', 'rw' => '001'],
        ['rt' => '002', 'kampung' => 'Sukamaju', 'rw' => '001'],
        ['rt' => '003', 'kampung' => 'Mekarsari', 'rw' => '001'],
        ['rt' => '004', 'kampung' => 'Cibiru Kulon', 'rw' => '002'],
        ['rt' => '005', 'kampung' => 'Cibiru Wetan', 'rw' => '002'],
        ['rt' => '006', 'kampung' => 'Babakan', 'rw' => '002'],
        ['rt' => '007', 'kampung' => 'Mekarsari Kidul', 'rw' => '002'],
        ['rt' => '008', 'kampung' => 'Pasirhuni', 'rw' => '003'],
        ['rt' => '009', 'kampung' => 'Kiarapayung', 'rw' => '003'],
        ['rt' => '010', 'kampung' => 'Cisalak', 'rw' => '003'],
        ['rt' => '011', 'kampung' => 'Cikadu', 'rw' => '004'],
        ['rt' => '012', 'kampung' => 'Cikadu', 'rw' => '004'],
        ['rt' => '013', 'kampung' => 'Binaan', 'rw' => '004'],
        ['rt' => '014', 'kampung' => 'Pasir Kedung', 'rw' => '004'],
        ['rt' => '015', 'kampung' => 'Patanjungan', 'rw' => '005'],
        ['rt' => '016', 'kampung' => 'Paleuh', 'rw' => '005'],
        ['rt' => '017', 'kampung' => 'Suka Sari', 'rw' => '005'],
        ['rt' => '018', 'kampung' => 'Perumahan Griya Asri', 'rw' => '005'],
        ['rt' => '019', 'kampung' => 'Mekarsari', 'rw' => '001'],
    ];

    /** @var list<array{name: string, username: string, rt: string, address?: string}> */
    private const RESIDENTS = [
        ['name' => 'AHMAD FAUZI', 'username' => 'ahmadfauzi', 'rt' => '011'],
        ['name' => 'SITI AMINAH', 'username' => 'sitiaminah', 'rt' => '003'],
        ['name' => 'NUR AISYAH PUTRI', 'username' => 'nuraisyahputri', 'rt' => '011'],
        ['name' => 'MARYAM', 'username' => 'maryam', 'rt' => '015'],
        ['name' => 'DEWI ANGGRAINI', 'username' => 'dewianggraini', 'rt' => '015'],
        ['name' => 'RATNA WULANDARI', 'username' => 'ratnawulandari', 'rt' => '015'],
        ['name' => 'LESTARI', 'username' => 'lestari', 'rt' => '011'],
        ['name' => 'ABDUL RAHMAN', 'username' => 'abdulrahman', 'rt' => '005', 'address' => 'PERUMAHAN GRIYA ASRI BLOK L7 NO.8'],
        ['name' => 'ROFIK', 'username' => 'rofik', 'rt' => '002'],
        ['name' => 'AGUS SALIM', 'username' => 'agussalim', 'rt' => '008'],
        ['name' => 'YUNI LESTARI', 'username' => 'yunilestari', 'rt' => '003', 'address' => 'JL. MELATI INDAH'],
        ['name' => 'INDAH PERMATA', 'username' => 'indahpermata', 'rt' => '008'],
        ['name' => 'SUMIATI', 'username' => 'sumiati', 'rt' => '017'],
        ['name' => 'MULYANI', 'username' => 'mulyani', 'rt' => '013'],
        ['name' => 'AYU LESTARI', 'username' => 'ayulestari', 'rt' => '011'],
        ['name' => 'MARTINI', 'username' => 'martini', 'rt' => '002'],
        ['name' => 'FITRIANI', 'username' => 'fitriani', 'rt' => '005'],
        ['name' => 'SUBHAN', 'username' => 'subhan', 'rt' => '010'],
        ['name' => 'LILIS SURYANI', 'username' => 'lilissuryani', 'rt' => '016'],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(RolesAndPermissionsSeeder::class);
            $rts = $this->seedRegions();
            $users = $this->seedUsers($rts);
            $this->seedVillageServiceArea($rts, $users['petugas'], $users['bendahara']);
            WasteMasterSeeder::seed($users['admin']);
        });

        $this->command?->info('Bootstrap production selesai: 1 desa, 5 RW, 19 wilayah RT/kampung, 19 warga, 4 akun pengelola, 1 area layanan, dan '.count(WasteMasterSeeder::TYPES).' jenis sampah.');
    }

    /** @return array<string, Rt> */
    private function seedRegions(): array
    {
        return RegionMutationGuard::run(function (): array {
            $village = Dusun::query()->updateOrCreate(
                ['code' => 'BS-DESA'],
                ['name' => 'Desa Binaan', 'is_active' => true],
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
            $user->roles()->syncWithoutDetaching([$wargaRole->id => ['assigned_by' => $user->id, 'reason' => 'Bootstrap production']]);
            $region = collect(self::REGIONS)->firstWhere('rt', $resident['rt']);
            $profile = CustomerProfile::query()->firstOrNew(['user_id' => $user->id]);
            $needsToken = $profile->qr_token_hash === null || $profile->qr_token_encrypted === null;
            $token = $needsToken ? QrToken::generate() : null;
            $customerNumber = $profile->customer_number;
            if ($customerNumber === null || str_starts_with($customerNumber, 'SH-')) {
                $customerNumber = 'CST-'.str_pad((string) ($index + 1), 8, '0', STR_PAD_LEFT);
            }
            $profile->forceFill([
                'customer_number' => $customerNumber,
                'rt_id' => $rts[$resident['rt']]->id,
                'address' => $resident['address'] ?? sprintf('%s, RT %s/RW %s, Desa Binaan', $region['kampung'], $resident['rt'], $region['rw']),
                'joined_at' => $profile->joined_at ?? now()->toDateString(),
                'qr_token_hash' => $needsToken ? $token?->hash() : $profile->qr_token_hash,
                'qr_token_encrypted' => $needsToken ? $token?->value() : $profile->qr_token_encrypted,
                'qr_rotated_at' => $needsToken ? now() : $profile->qr_rotated_at,
            ])->save();
        }

        $staff = [];
        foreach (['petugas', 'bendahara', 'admin', 'superadmin'] as $roleName) {
            $user = $this->upsertUser($roleName, ucfirst($roleName));
            $role = Role::query()->where('name', $roleName)->firstOrFail();
            $user->roles()->syncWithoutDetaching([$role->id => ['assigned_by' => $user->id, 'reason' => 'Bootstrap production']]);
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
                ['name' => 'Seluruh Desa Binaan'],
                ['is_active' => true],
            );
            $area->rts()->sync(array_map(static fn (Rt $rt): int => $rt->id, array_values($rts)));

            return $area;
        });

        foreach (['petugas' => $petugas, 'bendahara' => $bendahara] as $role => $user) {
            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                ['staff_number' => $role === 'petugas' ? 'STF-BS-001' : 'STF-BS-002', 'service_area_id' => $area->id, 'active_from' => now()->toDateString(), 'active_to' => null],
            );
            StaffServiceArea::query()->updateOrCreate(
                ['staff_profile_user_id' => $profile->user_id, 'service_area_id' => $area->id],
                ['active_from' => $profile->active_from, 'active_to' => null],
            );
        }
    }
}
