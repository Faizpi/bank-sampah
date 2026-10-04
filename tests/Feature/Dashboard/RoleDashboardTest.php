<?php

declare(strict_types=1);

namespace Tests\Feature\Dashboard;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
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
            ->assertSee('Belum ada setoran')
            ->assertSee('Setoran pertamamu akan muncul di sini.')
            ->assertSee('Navigasi warga')
            ->assertDontSee('Data Warga Lain');
    }

    public function test_citizen_dashboard_denies_an_authenticated_actor_without_own_profile_permission(): void
    {
        $actor = User::factory()->create();

        $this->actingAs($actor);

        Livewire::test(Dashboard::class)->assertForbidden();
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
            ->assertSee('Saat ada tugas yang ditugaskan kepada Anda, tugas tersebut akan muncul di sini.')
            ->assertSee('Selamat bertugas!')
            ->assertSee('Navigasi petugas')
            ->assertDontSee('Data Warga Lain');
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
            ->assertSee('Pencairan yang disetujui dan siap dibayar akan muncul di sini.')
            ->assertSee('Navigasi bendahara')
            ->assertSee('Selamat bertugas!')
            ->assertDontSee('Data Warga Lain');

        $source = file_get_contents(app_path('Livewire/Treasurer/Dashboard.php'));
        self::assertIsString($source);
        self::assertStringNotContainsString('::query(', $source);
        self::assertStringNotContainsString('DB::', $source);
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
