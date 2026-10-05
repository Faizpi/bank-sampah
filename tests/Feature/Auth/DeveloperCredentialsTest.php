<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LogoutUser;
use App\Domain\Communication\Models\Announcement;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeveloperUsersSeeder;
use Database\Seeders\LocalDataSeeder;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class DeveloperCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_seeder_creates_one_active_account_with_email_and_phone_for_every_role(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        foreach (DeveloperUsersSeeder::telephones() as $role => $phone) {
            $user = $this->devUser($role);

            self::assertSame(UserStatus::Active, $user->status, "Dev account invalid for role [{$role}].");
            self::assertNotNull($user->email);
            self::assertNotNull($user->phone);
            self::assertTrue($user->roles->pluck('name')->contains($role));
        }
    }

    public function test_public_demo_shortcuts_fill_the_matching_account_for_each_visible_role(): void
    {
        config()->set('app.demo_mode', true);

        foreach (['warga', 'petugas', 'bendahara'] as $role) {
            Livewire::test(LoginForm::class)
                ->call('fillDemo', $role)
                ->assertSet('phone', DeveloperUsersSeeder::telephone($role))
                ->assertSet('password', DeveloperUsersSeeder::DEV_PASSWORD);
        }
    }

    public function test_warga_petugas_and_bendahara_are_redirected_to_their_own_workspace_after_public_login(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        $dashboardRoutes = [
            'warga' => 'citizen.dashboard',
            'petugas' => 'officer.dashboard',
            'bendahara' => 'treasurer.dashboard',
        ];

        foreach ($dashboardRoutes as $role => $dashboardRoute) {
            $user = $this->devUser($role);

            Livewire::test(LoginForm::class)
                ->set('phone', $user->phone)
                ->set('password', DeveloperUsersSeeder::DEV_PASSWORD)
                ->call('login')
                ->assertHasNoErrors()
                ->assertRedirect(route($dashboardRoute));

            self::assertAuthenticatedAs($user);
            app(LogoutUser::class)->handle(request());
        }
    }

    public function test_admin_and_superadmin_public_login_use_the_backoffice_as_their_canonical_home(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        foreach (['admin', 'superadmin'] as $role) {
            Livewire::test(LoginForm::class)
                ->set('phone', $this->devUser($role)->phone)
                ->set('password', DeveloperUsersSeeder::DEV_PASSWORD)
                ->call('login')
                ->assertHasNoErrors()
                ->assertRedirect(route('filament.backoffice.home'));

            app(LogoutUser::class)->handle(request());
        }
    }

    public function test_seeded_bendahara_can_render_the_empty_report_route(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $bendahara = $this->devUser('bendahara');

        $this->actingAs($bendahara)
            ->get(route('treasurer.reports'))
            ->assertOk()
            ->assertSee('Laporan Pencairan')
            ->assertSee('Filter laporan')
            ->assertSee('Tidak ada hasil')
            ->assertSee('Rp 0');
    }

    public function test_admin_logs_in_through_the_filament_backoffice_form(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $admin = $this->devUser('admin');

        Livewire::test(FilamentLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => DeveloperUsersSeeder::DEV_PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        self::assertSame($admin->id, Filament::auth()->id());
    }

    public function test_admin_and_superadmin_can_open_the_backoffice_panel(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        self::assertTrue($this->devUser('admin')->canAccessPanel($this->backofficePanel()));
        self::assertTrue($this->devUser('superadmin')->canAccessPanel($this->backofficePanel()));
    }

    public function test_warga_has_a_customer_profile_and_petugas_bendahara_have_staff_profiles(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        self::assertInstanceOf(CustomerProfile::class, $this->devUser('warga')->customerProfile);
        self::assertInstanceOf(StaffProfile::class, $this->devUser('petugas')->staffProfile);
        self::assertInstanceOf(StaffProfile::class, $this->devUser('bendahara')->staffProfile);
        self::assertNull($this->devUser('admin')->customerProfile);
        self::assertNull($this->devUser('admin')->staffProfile);
    }

    public function test_dev_seeder_is_idempotent_and_does_not_duplicate_roles(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(DeveloperUsersSeeder::class);

        foreach (array_keys(DeveloperUsersSeeder::telephones()) as $role) {
            self::assertSame(1, Role::query()->where('name', $role)->count());
            self::assertSame(1, $this->devUser($role)->roles()->where('name', $role)->count());
        }
    }

    public function test_production_demo_data_requires_an_explicit_flag_and_a_strong_password(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', false);
        config()->set('app.demo_password', null);

        self::assertFalse(DeveloperUsersSeeder::canSeedDemoData());

        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'terlalu-pendek');

        self::assertFalse(DeveloperUsersSeeder::canSeedDemoData());

        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        self::assertTrue(DeveloperUsersSeeder::canSeedDemoData());
    }

    public function test_production_demo_data_uses_the_recent_environment_values_when_the_deploy_console_has_an_old_config_cache(): void
    {
        $originalMode = $_ENV['APP_DEMO_MODE'] ?? null;
        $originalPassword = $_ENV['APP_DEMO_PASSWORD'] ?? null;

        try {
            config()->set('app.env', 'production');
            config()->set('app.demo_mode', false);
            config()->set('app.demo_password', null);
            $_ENV['APP_DEMO_MODE'] = 'true';
            $_ENV['APP_DEMO_PASSWORD'] = 'KataSandiUji-Yang-Unik-2026';

            self::assertTrue(DeveloperUsersSeeder::canSeedDemoData());
            self::assertSame('KataSandiUji-Yang-Unik-2026', DeveloperUsersSeeder::password());
        } finally {
            if ($originalMode === null) {
                unset($_ENV['APP_DEMO_MODE']);
            } else {
                $_ENV['APP_DEMO_MODE'] = $originalMode;
            }

            if ($originalPassword === null) {
                unset($_ENV['APP_DEMO_PASSWORD']);
            } else {
                $_ENV['APP_DEMO_PASSWORD'] = $originalPassword;
            }
        }
    }

    public function test_demo_settings_are_read_from_the_private_project_environment_file_before_stale_cache_values(): void
    {
        $seeder = file_get_contents(base_path('database/seeders/DeveloperUsersSeeder.php'));

        self::assertNotFalse($seeder);
        self::assertStringContainsString("base_path('.env')", $seeder);
        self::assertStringContainsString('Dotenv::parse($contents)', $seeder);
    }

    public function test_explicit_production_demo_configuration_seeds_accounts_and_operational_sample_data(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        // This mirrors the shared-hosting deploy console, which invokes the
        // credential and operational seeders as two separate Artisan calls.
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);

        self::assertGreaterThanOrEqual(45, User::query()->count());
        self::assertNotNull($this->devUser('warga')->customerProfile);
        self::assertNotNull($this->devUser('petugas')->staffProfile);
        self::assertTrue($this->devUser('admin')->canAccessPanel($this->backofficePanel()));
        self::assertTrue($this->devUser('superadmin')->canAccessPanel($this->backofficePanel()));
    }

    public function test_operational_demo_seed_provides_active_future_pickup_capacity_for_every_demo_area(): void
    {
        $this->freezeDemoClock();
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);

        $demoAreaIds = ServiceArea::query()->where('name', 'like', 'Layanan Sindangheula %')->where('is_active', true)->pluck('id')->sort()->values()->all();
        $coveredAreaIds = PickupCapacity::query()
            ->where('is_active', true)
            ->whereDate('service_date', '>', today())
            ->distinct()
            ->pluck('service_area_id')
            ->sort()
            ->values()
            ->all();

        self::assertSame($demoAreaIds, $coveredAreaIds);
    }

    public function test_operational_demo_seed_has_two_active_areas_multi_area_staff_and_normalized_fixture_ids(): void
    {
        $clock = $this->freezeDemoClock();
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);

        $areas = ServiceArea::query()->where('name', 'like', 'Layanan Sindangheula %')->where('is_active', true)->orderBy('name')->get();
        self::assertSame(['Layanan Sindangheula Selatan', 'Layanan Sindangheula Utara'], $areas->pluck('name')->all());
        self::assertSame(0, ServiceArea::query()->where('name', 'Layanan Sindangheula Tengah')->where('is_active', true)->count());

        foreach (['petugas', 'bendahara'] as $role) {
            self::assertTrue(Hash::check(DeveloperUsersSeeder::password(), $this->devUser($role)->password));
        }
        foreach (['6281312345001', '6281312345002', DeveloperUsersSeeder::telephone('petugas'), DeveloperUsersSeeder::telephone('bendahara')] as $phone) {
            $profile = User::query()->where('phone', $phone)->firstOrFail()->staffProfile;
            self::assertNotNull($profile);
            self::assertSame(2, StaffServiceArea::query()->where('staff_profile_user_id', $profile->user_id)->whereDate('active_from', '<=', $clock->toDateString())->whereNull('active_to')->count());
        }

        $fixturePattern = '/^[A-Z]+-SH-'.$clock->format('Ym').'-\d{3}$/';
        foreach ([Deposit::query()->pluck('deposit_number'), WithdrawalRequest::query()->pluck('request_number'), GroceryRedemption::query()->pluck('request_number')] as $fixtureIds) {
            foreach ($fixtureIds as $fixtureId) {
                self::assertMatchesRegularExpression($fixturePattern, $fixtureId);
            }
        }
        self::assertSame(0, WithdrawalRequest::query()->whereNull('rt_id')->orWhereNull('service_area_id')->count());
        self::assertSame(0, GroceryRedemption::query()->whereNull('rt_id')->orWhereNull('service_area_id')->count());
    }

    public function test_operational_demo_reseed_deactivates_legacy_yusuf_and_converges_to_two_operational_staff_per_role(): void
    {
        $this->freezeDemoClock();
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');
        $this->seed(DeveloperUsersSeeder::class);
        $legacyArea = ServiceArea::query()->where('name', 'Layanan Sindangheula Tengah')->firstOrFail();
        $yusuf = User::factory()->create(['name' => 'Yusuf Maulana', 'phone' => '6281312345003']);
        $petugas = Role::query()->where('name', 'petugas')->firstOrFail();
        $yusuf->roles()->attach($petugas->id, ['assigned_by' => $yusuf->id, 'reason' => 'Legacy demo']);
        $profile = StaffProfile::query()->create(['user_id' => $yusuf->id, 'staff_number' => 'STF-SH-103', 'service_area_id' => $legacyArea->id, 'active_from' => now()->subMonth(), 'active_to' => null]);
        StaffServiceArea::query()->updateOrCreate(['staff_profile_user_id' => $profile->user_id, 'service_area_id' => $legacyArea->id], ['active_from' => now()->subMonth(), 'active_to' => null]);

        $this->seed(LocalDataSeeder::class);

        self::assertFalse($yusuf->fresh()->roles()->where('name', 'petugas')->exists());
        self::assertNotNull($profile->fresh()->active_to);
        self::assertSame(0, StaffServiceArea::query()->where('staff_profile_user_id', $yusuf->id)->whereNull('active_to')->count());
        self::assertSame(2, User::query()->whereHas('roles', fn ($query) => $query->where('name', 'petugas'))->whereHas('staffProfile', fn ($query) => $query->whereNull('active_to'))->count());
        self::assertSame(2, User::query()->whereHas('roles', fn ($query) => $query->where('name', 'bendahara'))->whereHas('staffProfile', fn ($query) => $query->whereNull('active_to'))->count());
    }

    public function test_operational_demo_seed_reactivates_future_capacity_fixtures_and_remains_idempotent(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);
        $capacity = PickupCapacity::query()->whereDate('service_date', today()->addDay())->firstOrFail();
        $capacity->forceFill(['is_active' => false, 'max_addresses' => 1, 'max_weight_kg' => '1.000', 'vehicle_label' => 'Lama'])->save();
        $count = PickupCapacity::query()->count();

        $this->seed(LocalDataSeeder::class);
        $this->seed(LocalDataSeeder::class);

        $capacity->refresh();
        self::assertTrue($capacity->is_active);
        self::assertSame(12, $capacity->max_addresses);
        self::assertSame('80.000', (string) $capacity->max_weight_kg);
        self::assertStringStartsWith('Kendaraan Layanan ', (string) $capacity->vehicle_label);
        self::assertSame($count, PickupCapacity::query()->count());
    }

    public function test_snapshot_backfill_resolves_only_single_active_area_and_fails_closed_for_ambiguous_or_unmapped_records(): void
    {
        $this->freezeDemoClock();
        $this->seed(DeveloperUsersSeeder::class);
        $admin = $this->devUser('admin');
        $manager = app(ManageRegions::class);
        $uniqueRt = $this->newBackfillRt($admin, 'UNIK');
        $ambiguousRt = $this->newBackfillRt($admin, 'AMBIGU');
        $unmappedRt = $this->newBackfillRt($admin, 'KOSONG');
        $uniqueArea = $manager->createServiceArea($admin, 'Area Backfill Tunggal', [$uniqueRt]);
        $manager->createServiceArea($admin, 'Area Backfill Ambigu Satu', [$ambiguousRt]);
        $manager->createServiceArea($admin, 'Area Backfill Ambigu Dua', [$ambiguousRt]);
        $uniqueCustomer = User::factory()->create();
        $uniqueCustomer->customerProfile()->create(['customer_number' => 'CST-BACKFILL-001', 'rt_id' => $uniqueRt->id, 'address' => 'Alamat unik', 'joined_at' => now()->toDateString()]);
        $ambiguousCustomer = User::factory()->create();
        $ambiguousCustomer->customerProfile()->create(['customer_number' => 'CST-BACKFILL-002', 'rt_id' => $ambiguousRt->id, 'address' => 'Alamat ambigu', 'joined_at' => now()->toDateString()]);
        $unmappedCustomer = User::factory()->create();
        $unmappedCustomer->customerProfile()->create(['customer_number' => 'CST-BACKFILL-003', 'rt_id' => $unmappedRt->id, 'address' => 'Alamat tanpa area', 'joined_at' => now()->toDateString()]);
        $package = GroceryPackage::query()->create(['code' => 'PKT-BACKFILL', 'name' => 'Paket Backfill', 'contents' => 'Beras', 'value' => 10_000, 'status' => 'aktif']);

        foreach ([$uniqueCustomer, $ambiguousCustomer, $unmappedCustomer] as $index => $customer) {
            DB::table('withdrawal_requests')->insert(['request_number' => 'WDR-BACKFILL-00'.($index + 1), 'customer_id' => $customer->id, 'requested_by_id' => $customer->id, 'amount' => 10_000, 'status' => 'menunggu_verifikasi', 'created_at' => now(), 'updated_at' => now()]);
            DB::table('grocery_redemptions')->insert(['request_number' => 'GRC-BACKFILL-00'.($index + 1), 'customer_id' => $customer->id, 'requested_by_id' => $customer->id, 'grocery_package_id' => $package->id, 'value_snapshot' => 10_000, 'package_snapshot' => json_encode(['code' => $package->code]), 'status' => 'menunggu_verifikasi', 'created_at' => now(), 'updated_at' => now()]);
        }

        $migration = require base_path('database/migrations/2026_08_21_140000_backfill_area_snapshots_for_existing_requests.php');
        $migration->up();

        foreach (['withdrawal_requests', 'grocery_redemptions'] as $table) {
            self::assertSame($uniqueRt->id, DB::table($table)->where('customer_id', $uniqueCustomer->id)->value('rt_id'));
            self::assertSame($uniqueArea->id, DB::table($table)->where('customer_id', $uniqueCustomer->id)->value('service_area_id'));
            self::assertNull(DB::table($table)->where('customer_id', $ambiguousCustomer->id)->value('rt_id'));
            self::assertNull(DB::table($table)->where('customer_id', $unmappedCustomer->id)->value('service_area_id'));
        }
    }

    public function test_operational_demo_seed_reactivates_existing_master_data_required_by_its_fixtures(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        $this->seed(DeveloperUsersSeeder::class);
        [$category, $unit, $condition] = WasteMasterMutationGuard::run(fn (): array => [
            WasteCategory::query()->create(['code' => 'PLASTIK', 'name' => 'Plastik', 'is_active' => false]),
            WasteUnit::query()->create([
                'code' => 'KG',
                'name' => 'Kilogram',
                'symbol' => 'kg',
                'classification' => WasteUnit::CLASSIFICATION_WEIGHT,
                'conversion_factor_to_kg' => '1.000000',
                'is_active' => false,
            ]),
            WasteCondition::query()->create(['code' => 'BERSIH', 'name' => 'Bersih', 'is_active' => false]),
        ]);

        $this->seed(LocalDataSeeder::class);

        self::assertTrue($category->fresh()->is_active);
        self::assertTrue($unit->fresh()->is_active);
        self::assertTrue($condition->fresh()->is_active);
    }

    public function test_operational_demo_seed_has_rolling_month_lifecycles_and_public_content(): void
    {
        $clock = $this->freezeDemoClock();
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);

        self::assertTrue(MobileService::query()->where('status', MobileServiceStatus::Closed)->where('ends_at', '<', $clock)->exists());
        self::assertTrue(MobileService::query()->where('status', MobileServiceStatus::Open)->where('starts_at', '<=', $clock)->where('ends_at', '>=', $clock)->exists());
        self::assertTrue(MobileService::query()->where('status', MobileServiceStatus::Published)->where('starts_at', '>', $clock)->where('starts_at', '<=', $clock->addDays(30))->exists());
        self::assertSame(2, PickupCapacity::query()->whereDate('service_date', $clock->toDateString())->where('is_active', true)->distinct('service_area_id')->count('service_area_id'));
        self::assertSame(2, PickupCapacity::query()->whereDate('service_date', $clock->addDays(30)->toDateString())->where('is_active', true)->distinct('service_area_id')->count('service_area_id'));
        self::assertTrue(PickupRequest::query()->whereIn('status', [PickupStatus::Completed, PickupStatus::Scheduled, PickupStatus::Accepted, PickupStatus::PendingReview])->exists());
        self::assertSame(0, PickupRequest::query()->where('status', PickupStatus::Completed)->whereNull('deposit_id')->count());
        self::assertGreaterThan(0, Deposit::query()->where('method', 'penjemputan')->count());
        self::assertGreaterThan(0, Deposit::query()->where('method', 'keliling')->count());
        self::assertTrue(WithdrawalRequest::query()->where('status', WithdrawalStatus::Paid)->whereNotNull('receipt_ledger_entry_id')->whereNotNull('payer_id')->exists());
        self::assertTrue(GroceryRedemption::query()->where('status', GroceryStatus::Completed)->whereNotNull('receipt_ledger_entry_id')->whereNotNull('handover_actor_id')->exists());
        self::assertGreaterThanOrEqual(2, CollectionTarget::query()->whereDate('period_end', '>=', $clock->addDays(30)->toDateString())->count());
        self::assertTrue(Announcement::query()->whereDate('publish_end', '>=', $clock->addDays(30)->toDateString())->exists());
        self::assertTrue(StatisticPublication::query()->where('publication_key', 'public-dashboard')->where('is_active', true)->where('privacy_threshold', '>=', 5)->exists());

        $counts = [Deposit::query()->count(), PickupRequest::query()->count(), WithdrawalRequest::query()->count(), GroceryRedemption::query()->count(), MobileService::query()->count()];
        $this->seed(LocalDataSeeder::class);
        self::assertSame($counts, [Deposit::query()->count(), PickupRequest::query()->count(), WithdrawalRequest::query()->count(), GroceryRedemption::query()->count(), MobileService::query()->count()]);
    }

    public function test_public_mobile_schedule_renders_after_explicit_demo_data_is_seeded(): void
    {
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        $this->seed(DatabaseSeeder::class);

        $this->get(route('public.mobile-schedule'))
            ->assertOk()
            ->assertSee('Jadwal aktif')
            ->assertSee('Halaman Kantor Desa Sindangheula');
    }

    private function newBackfillRt(User $admin, string $suffix): Rt
    {
        $manager = app(ManageRegions::class);
        $dusun = $manager->createDusun($admin, 'DSN-BACKFILL-'.$suffix, 'Dusun Backfill '.$suffix);
        $rw = $manager->createRw($admin, $dusun, 'RW-BACKFILL-'.$suffix, 'RW Backfill '.$suffix);

        return $manager->createRt($admin, $rw, 'RT-BACKFILL-'.$suffix, 'RT Backfill '.$suffix);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    private function freezeDemoClock(): CarbonImmutable
    {
        $clock = CarbonImmutable::create(2025, 2, 14, 10, 0, 0, 'Asia/Jakarta');
        CarbonImmutable::setTestNow($clock);

        return $clock;
    }

    private function devUser(string $role): User
    {
        return User::query()->where('phone', DeveloperUsersSeeder::telephone($role))->firstOrFail();
    }

    private function backofficePanel(): Panel
    {
        return Filament::getPanel('backoffice');
    }
}
