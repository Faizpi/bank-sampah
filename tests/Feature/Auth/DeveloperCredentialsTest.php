<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LogoutUser;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeveloperUsersSeeder;
use Database\Seeders\LocalDataSeeder;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Laporan Setoran')
            ->assertSee('Filter laporan')
            ->assertSee('Tidak ada hasil')
            ->assertSee('0.000');
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
        config()->set('app.env', 'production');
        config()->set('app.demo_mode', true);
        config()->set('app.demo_password', 'KataSandiUji-Yang-Unik-2026');

        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(LocalDataSeeder::class);

        $demoAreaIds = ServiceArea::query()->where('name', 'like', 'Layanan Sindangheula %')->pluck('id')->sort()->values()->all();
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

    private function devUser(string $role): User
    {
        return User::query()->where('phone', DeveloperUsersSeeder::telephone($role))->firstOrFail();
    }

    private function backofficePanel(): Panel
    {
        return Filament::getPanel('backoffice');
    }
}
