<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Http\Middleware\RequirePermission;
use App\Livewire\Citizen\Dashboard;
use App\Livewire\Officer\Dashboard as OfficerDashboard;
use App\Livewire\Treasurer\Dashboard as TreasurerDashboard;
use App\Models\User;
use Illuminate\Auth\Middleware\Authenticate;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;
use Livewire\Livewire;
use Tests\TestCase;

final class RoleDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_dashboard_renders_only_the_authenticated_citizen_safe_initial_state(): void
    {
        $citizen = User::factory()->create(['name' => 'Siti Aman']);
        $otherCitizen = User::factory()->create(['name' => 'Data Warga Lain']);
        $this->grant($citizen, 'warga', 'profile.view');
        $this->grant($otherCitizen, 'warga', 'profile.view');

        $this->actingAs($citizen->fresh())
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Halo, Siti Aman!')
            ->assertSee('Belum ada saldo')
            ->assertSee('Navigasi warga')
            ->assertDontSee('Data Warga Lain');
    }

    public function test_citizen_dashboard_denies_an_authenticated_actor_without_own_profile_permission(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        Livewire::test(Dashboard::class)->assertForbidden();
    }

    public function test_citizen_dashboard_excludes_paid_withdrawals_from_active_requests(): void
    {
        $citizen = User::factory()->create(['name' => 'Siti Aman']);
        $this->grant($citizen, 'warga', 'profile.view');
        WithdrawalRequest::query()->create([
            'request_number' => 'WDR-DASHBOARD-AKTIF',
            'customer_id' => $citizen->id,
            'requested_by_id' => $citizen->id,
            'amount' => 12_345,
            'status' => WithdrawalStatus::PendingVerification,
        ]);
        WithdrawalRequest::query()->create([
            'request_number' => 'WDR-DASHBOARD-DIBAYAR',
            'customer_id' => $citizen->id,
            'requested_by_id' => $citizen->id,
            'amount' => 54_321,
            'status' => WithdrawalStatus::Paid,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('Rp12.345')
            ->assertDontSee('Rp54.321');
    }

    public function test_citizen_dashboard_shows_only_its_active_grocery_requests_with_a_detail_action(): void
    {
        $citizen = User::factory()->create();
        $otherCitizen = User::factory()->create();
        $this->grant($citizen, 'warga', 'profile.view');
        GroceryRedemption::factory()->for($citizen, 'customer')->create([
            'request_number' => 'GRC-DASHBOARD-AKTIF',
            'status' => GroceryStatus::ReadyForPickup,
        ]);
        GroceryRedemption::factory()->for($citizen, 'customer')->create([
            'request_number' => 'GRC-DASHBOARD-SELESAI',
            'status' => GroceryStatus::Completed,
        ]);
        GroceryRedemption::factory()->for($otherCitizen, 'customer')->create([
            'request_number' => 'GRC-DASHBOARD-LAIN',
            'status' => GroceryStatus::ReadyForPickup,
        ]);

        $this->actingAs($citizen)
            ->get(route('citizen.dashboard'))
            ->assertOk()
            ->assertSee('GRC-DASHBOARD-AKTIF')
            ->assertSee('href="'.route('citizen.grocery.show', GroceryRedemption::query()->where('request_number', 'GRC-DASHBOARD-AKTIF')->sole()).'"', false)
            ->assertDontSee('GRC-DASHBOARD-SELESAI')
            ->assertDontSee('GRC-DASHBOARD-LAIN');
    }

    public function test_officer_dashboard_renders_a_safe_task_empty_state_without_listing_visible_users(): void
    {
        $officer = User::factory()->create(['name' => 'Budi Petugas']);
        $otherUser = User::factory()->create(['name' => 'Data Warga Lain']);
        $this->grant($officer, 'petugas', 'user.view');

        $this->actingAs($officer->fresh())
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Tugas hari ini')
            ->assertSee('Belum ada tugas hari ini')
            ->assertSee('Navigasi petugas')
            ->assertDontSee('Identifikasi Warga')
            ->assertDontSee('Tugas Sembako')
            ->assertDontSee('Jadwal Keliling')
            ->assertDontSee('Profil Akun')
            ->assertDontSee('Data Warga Lain');
    }

    public function test_officer_dashboard_promotes_an_open_assigned_mobile_service_to_focus_now(): void
    {
        $officer = User::factory()->create();
        $otherOfficer = User::factory()->create();
        $this->grant($officer, 'petugas', 'user.view', 'mobile-service.view', 'mobile-service.operate');
        $this->grant($otherOfficer, 'petugas-lain', 'user.view', 'mobile-service.view', 'mobile-service.operate');

        $assignedService = MobileService::query()->create([
            'service_number' => 'MS-FOCUS-001',
            'point' => 'Balai RW 02',
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addHour(),
            'status' => MobileServiceStatus::Open,
            'capacity' => 20,
            'served_count' => 0,
            'created_by' => $officer->id,
        ]);
        $assignedService->staff()->attach($officer);
        $otherService = MobileService::query()->create([
            'service_number' => 'MS-HIDDEN-002',
            'point' => 'Data Wilayah Lain',
            'starts_at' => now()->subMinutes(15),
            'ends_at' => now()->addHour(),
            'status' => MobileServiceStatus::Open,
            'capacity' => 20,
            'served_count' => 0,
            'created_by' => $otherOfficer->id,
        ]);
        $otherService->staff()->attach($otherOfficer);

        $this->actingAs($officer->fresh())
            ->get(route('officer.dashboard'))
            ->assertOk()
            ->assertSee('Fokus sekarang')
            ->assertSee('Layani titik keliling sekarang')
            ->assertSee('Balai RW 02')
            ->assertDontSee('Data Wilayah Lain');
    }

    public function test_officer_dashboard_rows_wrap_long_mobile_content_instead_of_truncating_it(): void
    {
        $view = file_get_contents(resource_path('views/livewire/officer/dashboard.blade.php'));

        self::assertIsString($view);
        self::assertStringContainsString('flex-col items-start gap-3', $view);
        self::assertStringContainsString('break-words text-body-sm text-text-secondary', $view);
        self::assertStringNotContainsString('block truncate text-body-sm', $view);
    }

    public function test_officer_dashboard_denies_an_authenticated_actor_without_user_view_permission(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        Livewire::test(OfficerDashboard::class)->assertForbidden();
    }

    public function test_treasurer_dashboard_renders_a_safe_task_empty_state_without_disclosing_future_domain_data(): void
    {
        $treasurer = User::factory()->create(['name' => 'Rina Bendahara']);
        $otherUser = User::factory()->create(['name' => 'Data Warga Lain']);
        $this->grant($treasurer, 'bendahara', 'withdrawal.view');

        $this->actingAs($treasurer->fresh())
            ->get(route('treasurer.dashboard'))
            ->assertOk()
            ->assertSee('Tugas hari ini')
            ->assertSee('Pencairan siap dibayar')
            ->assertSee('Belum ada pencairan siap dibayar')
            ->assertSee('Tidak ada pembayaran yang memerlukan tindakan Anda saat ini')
            ->assertSee('Navigasi bendahara')
            ->assertDontSee('Bayar pencairan')
            ->assertDontSee('Data Warga Lain');

        $source = file_get_contents(app_path('Livewire/Treasurer/Dashboard.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString('::query(', $source);
        self::assertStringNotContainsString('DB::', $source);
    }

    public function test_treasurer_dashboard_places_payment_action_before_its_informational_total(): void
    {
        $view = file_get_contents(resource_path('views/livewire/treasurer/dashboard.blade.php'));

        self::assertIsString($view);
        $actionPosition = strpos($view, 'Mulai pembayaran');
        $totalPosition = strpos($view, 'Total nominal dalam');
        self::assertNotFalse($actionPosition);
        self::assertNotFalse($totalPosition);
        self::assertLessThan($totalPosition, $actionPosition);
    }

    public function test_treasurer_dashboard_denies_an_authenticated_actor_without_withdrawal_view_permission(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        Livewire::test(TreasurerDashboard::class)->assertForbidden();
    }

    public function test_citizen_dashboard_route_has_the_exact_effective_middleware_contract(): void
    {
        $route = Route::getRoutes()->getByName('citizen.dashboard');

        self::assertNotNull($route);
        self::assertSame([
            'web',
            'auth',
            'session.fresh:30',
            'permission:profile.view',
        ], $route->middleware());
        self::assertSame($this->gatheredDashboardMiddleware('profile.view'), app('router')->gatherRouteMiddleware($route));
    }

    public function test_officer_dashboard_route_has_the_exact_effective_middleware_contract(): void
    {
        $route = Route::getRoutes()->getByName('officer.dashboard');

        self::assertNotNull($route);
        self::assertSame([
            'web',
            'auth',
            'session.fresh:30',
            'permission:user.view',
        ], $route->middleware());
        self::assertSame($this->gatheredDashboardMiddleware('user.view'), app('router')->gatherRouteMiddleware($route));
    }

    public function test_treasurer_dashboard_route_has_the_exact_effective_middleware_contract(): void
    {
        $route = Route::getRoutes()->getByName('treasurer.dashboard');

        self::assertNotNull($route);
        self::assertSame([
            'web',
            'auth',
            'session.fresh:30',
            'permission:withdrawal.view',
        ], $route->middleware());
        self::assertSame($this->gatheredDashboardMiddleware('withdrawal.view'), app('router')->gatherRouteMiddleware($route));
    }

    public function test_citizen_dashboard_redirects_a_stale_session_to_login_without_rendering_dashboard_data(): void
    {
        $citizen = User::factory()->create(['name' => 'Siti Aman']);
        $this->grant($citizen, 'warga', 'profile.view');

        $this->assertStaleDashboardSessionRedirectsToLogin($citizen, 'citizen.dashboard');
    }

    public function test_officer_dashboard_redirects_a_stale_session_to_login_without_rendering_dashboard_data(): void
    {
        $officer = User::factory()->create(['name' => 'Budi Petugas']);
        $this->grant($officer, 'petugas', 'user.view');

        $this->assertStaleDashboardSessionRedirectsToLogin($officer, 'officer.dashboard');
    }

    public function test_treasurer_dashboard_redirects_a_stale_session_to_login_without_rendering_dashboard_data(): void
    {
        $treasurer = User::factory()->create(['name' => 'Rina Bendahara']);
        $this->grant($treasurer, 'bendahara', 'withdrawal.view');

        $this->assertStaleDashboardSessionRedirectsToLogin($treasurer, 'treasurer.dashboard');
    }

    private function assertStaleDashboardSessionRedirectsToLogin(User $user, string $routeName): void
    {
        $this->withSession([
            EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(30)->getTimestamp(),
        ])
            ->actingAs($user)
            ->get(route($routeName))
            ->assertRedirectToRoute('login');

        $this->assertGuest();
    }

    /** @return list<string> */
    private function gatheredDashboardMiddleware(string $permission): array
    {
        return [
            EncryptCookies::class,
            AddQueuedCookiesToResponse::class,
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
            Authenticate::class,
            SubstituteBindings::class,
            EnsureSessionIsFresh::class.':30',
            RequirePermission::class.':'.$permission,
        ];
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => "Test role {$roleName}"],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);
    }
}
