<?php

declare(strict_types=1);

namespace Tests\Fixtures;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use App\Support\Auth\DemoCredentials;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

/** Isolated workflow fixture, never called by application seeding. */
final class WorkflowFixture extends Seeder
{
    private const ACCOUNTS = [
        'warga' => ['username' => 'warga', 'phone' => '6281234567801', 'email' => 'warga@sindangheula.dev', 'staff' => false, 'customer' => true],
        'petugas' => ['username' => 'petugas', 'phone' => '6281234567802', 'email' => 'petugas@sindangheula.dev', 'staff' => true, 'customer' => false],
        'bendahara' => ['username' => 'bendahara', 'phone' => '6281234567803', 'email' => 'bendahara@sindangheula.dev', 'staff' => true, 'customer' => false],
        'admin' => ['username' => 'admin', 'phone' => '6281234567804', 'email' => 'admin@sindangheula.dev', 'staff' => false, 'customer' => false],
    ];

    /** @var array<string, string> */
    private const DISPLAY_NAMES = [
        'warga' => 'Siti Aminah',
        'petugas' => 'Dadan Hidayat',
        'bendahara' => 'Dewi Lestari',
        'admin' => 'Rina Marlina',
    ];

    public function run(): void
    {
        $connection = DB::connection();
        $safeSqlite = $connection->getDriverName() === 'sqlite' && $connection->getDatabaseName() === ':memory:';
        $safeMysql = $connection->getDriverName() === 'mysql'
            && preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', $connection->getDatabaseName()) === 1
            && in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true);
        if (config('app.env') !== 'testing' || (! $safeSqlite && ! $safeMysql)) {
            throw new \LogicException('Fixture hanya boleh memakai SQLite memori atau MySQL disposable pada testing.');
        }
        RegionMutationGuard::run(fn () => $this->seedAccounts());
        $this->seedMaster();
    }

    private function seedAccounts(): void
    {
        DemoCredentials::requireDemoDataConfiguration();

        // All generated accounts intentionally share the configured demo
        // password. Hash it once so a web-based deployment on shared hosting
        // does not spend its whole request budget repeating bcrypt work.
        $passwordHash = Hash::make(DemoCredentials::password());

        foreach (self::ACCOUNTS as $roleName => $account) {
            $user = User::query()->updateOrCreate(
                ['username' => $account['username']],
                [
                    'name' => self::DISPLAY_NAMES[$roleName],
                    'phone' => $account['phone'] !== '' ? $account['phone'] : null,
                    'email' => $account['email'] !== '' ? $account['email'] : null,
                    'email_verified_at' => now(),
                    'password' => $passwordHash,
                    'status' => UserStatus::Active,
                    'role' => $roleName,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );

            $this->ensureProfiles($user, $roleName, $account['customer'], $account['staff']);

            $this->command?->info("Created dev account for role [{$roleName}]: {$account['email']} / phone {$account['phone']}");
        }
    }

    private function ensureProfiles(User $user, string $roleName, bool $customer, bool $staff): void
    {
        if ($customer) {
            CustomerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'customer_number' => 'CST-00000001',
                    'rt_id' => $this->localRt()->id,
                    'address' => 'Kampung Sukamaju RT 01/RW 02, Sindangheula',
                    'joined_at' => now()->toDateString(),
                ],
            );

            // The demo identity must ship a working QR card. Seed the token when
            // the profile has none (fresh seed) or only carries an orphan hash
            // whose plaintext was never stored. Re-seeding never rotates a token
            // that is already complete, so existing demo QR stays valid.
            /** @var CustomerProfile $profile */
            $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
            if ($profile->qr_token_hash === null || $profile->qr_token_encrypted === null) {
                $token = QrToken::generate();
                $profile->forceFill([
                    'qr_token_hash' => $token->hash(),
                    'qr_token_encrypted' => $token->value(),
                    'qr_rotated_at' => now(),
                ])->save();
            }
        }

        if ($staff) {
            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'staff_number' => $roleName === 'bendahara' ? 'STF-SH-002' : 'STF-SH-001',
                    'service_area_id' => $this->localServiceArea()->id,
                    'active_from' => now()->subYear()->toDateString(),
                    'active_to' => null,
                ],
            );
            $this->localServiceArea()->rts()->syncWithoutDetaching([$this->localRt()->id]);
            $profile->serviceAreas()->updateOrCreate(['service_area_id' => $this->localServiceArea()->id], ['active_from' => now()->subYear()->toDateString(), 'active_to' => null]);
        }
    }

    private function localServiceArea(): ServiceArea
    {
        return ServiceArea::query()->firstOrCreate(
            ['name' => 'Layanan Sindangheula Tengah'],
            ['is_active' => true],
        );
    }

    private function localRt(): Rt
    {
        $dusun = Dusun::query()->firstOrCreate(
            ['code' => 'DSN-SH-TENGAH'],
            ['name' => 'Dusun Sindangheula Tengah', 'is_active' => true],
        );
        $rw = Rw::query()->firstOrCreate(
            ['dusun_id' => $dusun->id, 'code' => 'DSN-SH-TENGAH-RW-02'],
            ['name' => 'RW 02 Sindangheula Tengah', 'is_active' => true],
        );

        return Rt::query()->firstOrCreate(
            ['rw_id' => $rw->id, 'code' => 'DSN-SH-TENGAH-RW-02-RT-01'],
            ['name' => 'RT 01 RW 02', 'is_active' => true],
        );
    }

    private function seedMaster(): void
    {
        DemoCredentials::requireDemoDataConfiguration();
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
