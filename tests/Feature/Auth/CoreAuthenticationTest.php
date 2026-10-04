<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LogoutUser;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Livewire\Auth\LoginForm;
use App\Models\User;
use App\Support\Auth\LoginRateLimiter;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;
use Tests\TestCase;

final class CoreAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        RateLimiter::clear(LoginRateLimiter::key('6281234567890', '127.0.0.1'));
    }

    public function test_active_user_can_login_with_normalized_phone_and_session_is_regenerated(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 10:15:00');
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
            'last_login_at' => null,
        ]);
        $oldSessionId = session()->getId();

        Livewire::test(LoginForm::class)
            ->set('phone', '0812-3456-7890')
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors();

        $this->assertAuthenticatedAs($user);
        self::assertNotSame($oldSessionId, session()->getId());
        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'last_login_at' => now()->format('Y-m-d H:i:s'),
        ]);
        $audit = AuditLog::query()->sole();

        self::assertSame($user->id, $audit->getAttribute('actor_id'));
        self::assertSame('access.login.succeeded', $audit->getAttribute('action'));
        self::assertSame(User::class, $audit->getAttribute('auditable_type'));
        self::assertSame($user->id, $audit->getAttribute('auditable_id'));
        self::assertNotEmpty($audit->getAttribute('correlation_id'));
        self::assertSame([], $audit->getAttribute('old_values'));
        self::assertSame([], $audit->getAttribute('new_values'));
    }

    public function test_invalid_credentials_and_non_active_accounts_share_one_generic_failure(): void
    {
        $cases = [
            [UserStatus::Active, 'kata-sandi-salah'],
            [UserStatus::PendingVerification, 'rahasia-yang-kuat'],
            [UserStatus::Rejected, 'rahasia-yang-kuat'],
            [UserStatus::Inactive, 'rahasia-yang-kuat'],
        ];

        foreach ($cases as $index => [$status, $password]) {
            $user = User::factory()->create([
                'phone' => '628123456'.str_pad((string) $index, 4, '0', STR_PAD_LEFT),
                'password' => Hash::make('rahasia-yang-kuat'),
                'status' => $status,
                'verified_at' => $status === UserStatus::Active ? now() : null,
                'rejection_reason' => $status === UserStatus::Rejected ? 'Tidak lolos verifikasi.' : null,
                'last_login_at' => null,
            ]);

            Livewire::test(LoginForm::class)
                ->set('phone', $user->phone)
                ->set('password', $password)
                ->call('login')
                ->assertHasErrors(['phone' => 'Kredensial tidak valid atau akun tidak dapat digunakan.'])
                ->assertDispatched('login-invalid');

            $this->assertGuest();
            self::assertNull($user->refresh()->last_login_at);
        }

        $audits = AuditLog::query()->where('action', 'access.login.rejected')->get();

        self::assertCount(count($cases), $audits);
        foreach ($audits as $audit) {
            self::assertSame('system', $audit->getAttribute('actor_type'));
            self::assertSame(['reason' => 'invalid_credentials'], $audit->getAttribute('new_values'));
        }
    }

    public function test_unknown_phone_uses_the_same_generic_failure_without_creating_a_session(): void
    {
        Livewire::test(LoginForm::class)
            ->set('phone', '0812 3456 7890')
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasErrors(['phone' => 'Kredensial tidak valid atau akun tidak dapat digunakan.']);

        $this->assertGuest();
        $audit = AuditLog::query()->sole();

        self::assertSame('access.login.rejected', $audit->getAttribute('action'));
        self::assertSame('system', $audit->getAttribute('actor_type'));
        self::assertSame(['reason' => 'invalid_credentials'], $audit->getAttribute('new_values'));
        self::assertStringNotContainsString('6281234567890', json_encode($audit->getAttribute('new_values'), JSON_THROW_ON_ERROR));
    }

    public function test_rate_limited_login_is_audited_without_creating_a_session(): void
    {
        $phone = '6281234567890';
        $ip = request()->ip() ?? 'unknown';

        for ($attempt = 0; $attempt < LoginRateLimiter::MAX_ATTEMPTS; $attempt++) {
            RateLimiter::hit(LoginRateLimiter::key($phone, $ip), 60);
        }

        Livewire::test(LoginForm::class)
            ->set('phone', '0812-3456-7890')
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasErrors(['phone' => 'Kredensial tidak valid atau akun tidak dapat digunakan.']);

        $this->assertGuest();
        $audit = AuditLog::query()->sole();

        self::assertSame('access.login.rejected', $audit->getAttribute('action'));
        self::assertSame(['reason' => 'rate_limited'], $audit->getAttribute('new_values'));
    }

    public function test_successful_login_does_not_clear_a_below_limit_counter_for_the_same_normalized_phone_and_ip(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $ip = request()->ip() ?? 'unknown';
        $key = LoginRateLimiter::key($user->phone, $ip);

        for ($attempt = 0; $attempt < LoginRateLimiter::MAX_ATTEMPTS - 1; $attempt++) {
            Livewire::test(LoginForm::class)
                ->set('phone', '0812-3456-7890')
                ->set('password', 'kata-sandi-salah')
                ->call('login')
                ->assertHasErrors(['phone' => 'Kredensial tidak valid atau akun tidak dapat digunakan.']);

            $this->assertGuest();
        }

        Livewire::test(LoginForm::class)
            ->set('phone', '0812-3456-7890')
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors();

        self::assertSame(LoginRateLimiter::MAX_ATTEMPTS - 1, RateLimiter::attempts($key));
    }

    public function test_logout_forgets_authentication_invalidates_session_and_regenerates_csrf_token(): void
    {
        $user = User::factory()->create();
        Auth::login($user);
        session()->put('private-data', 'secret');
        $oldSessionId = session()->getId();
        $oldToken = session()->token();

        app(LogoutUser::class)->handle(request());

        $this->assertGuest();
        self::assertNotSame($oldSessionId, session()->getId());
        self::assertNotSame($oldToken, session()->token());
        self::assertNull(session('private-data'));
        $audit = AuditLog::query()->sole();

        self::assertSame('access.logout.succeeded', $audit->getAttribute('action'));
        self::assertSame($user->id, $audit->getAttribute('actor_id'));
    }

    public function test_login_route_mounts_the_existing_form_for_guests_and_redirects_authenticated_users(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSeeHtml('wire:name="auth.login-form"');

        $user = User::factory()->create();
        $this->grant($user, 'warga', 'profile.view');

        $this->actingAs($user)
            ->get(route('login'))
            ->assertRedirect(route('citizen.dashboard'));
    }

    public function test_successful_login_redirects_only_to_an_existing_authorized_private_intended_url(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        Route::middleware('auth')
            ->get('/auth-test-intended', static fn (): string => 'private');
        $intendedUrl = url('/auth-test-intended?source=protected');
        session()->put('url.intended', $intendedUrl);

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect($intendedUrl);
    }

    public function test_successful_login_falls_back_to_the_next_authorized_dashboard_when_role_dashboard_permission_is_missing(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $this->grant($user, 'bendahara', 'user.view');

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('officer.dashboard'));
    }

    public function test_successful_login_falls_back_home_when_no_dashboard_permission_is_allowed(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));
    }

    public function test_successful_login_rejects_an_intended_url_without_its_required_permission(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $this->grant($user, 'warga', 'profile.view');
        Route::middleware(['auth', 'permission:user.view'])
            ->get('/auth-test-permission-intended', static fn (): string => 'private');
        $intendedUrl = url('/auth-test-permission-intended?source=permission');
        session()->put('url.intended', $intendedUrl);

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('citizen.dashboard'));
    }

    public function test_successful_login_preserves_bendahara_precedence_over_other_staff_roles(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $this->grant($user, 'petugas', 'user.view');
        $this->grant($user, 'bendahara', 'withdrawal.view');

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('treasurer.dashboard'));
    }

    public function test_authenticated_public_dashboard_ctas_use_the_next_authorized_dashboard(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'bendahara', 'user.view');

        $html = $this->actingAs($user)
            ->get(route('home'))
            ->assertOk()
            ->getContent();

        self::assertSame(3, substr_count($html, 'href="'.route('officer.dashboard').'"'));
        self::assertStringNotContainsString('href="'.route('treasurer.dashboard').'"', $html);
    }

    public function test_login_rejects_a_suffix_host_intended_url(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $this->grant($user, 'warga', 'profile.view');
        Route::middleware('auth')
            ->get('/auth-test-hostile-intended', static fn (): string => 'private');
        $hostileUrl = request()->getScheme().'://attacker.test/auth-test-hostile-intended';
        session()->put('url.intended', $hostileUrl);

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('citizen.dashboard'));
    }

    public function test_login_rejects_a_malformed_same_origin_intended_url(): void
    {
        $user = User::factory()->create([
            'phone' => '6281234567890',
            'password' => Hash::make('rahasia-yang-kuat'),
            'status' => UserStatus::Active,
        ]);
        $this->grant($user, 'warga', 'profile.view');
        Route::middleware('auth')
            ->get('/auth-test-malformed-intended', static fn (): string => 'private');
        session()->put('url.intended', url('/auth-test-malformed-intended').'\\invalid');

        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', 'rahasia-yang-kuat')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('citizen.dashboard'));
    }

    public function test_logout_endpoint_rejects_a_missing_csrf_token_without_ending_the_session(): void
    {
        $user = User::factory()->create();

        self::assertContains('web', Route::getRoutes()->getByName('logout')->gatherMiddleware());

        $environment = $this->app['env'];
        $this->app['env'] = 'production';

        try {
            $this->withMiddleware()
                ->actingAs($user)
                ->post(route('logout'))
                ->assertStatus(419);
        } finally {
            $this->app['env'] = $environment;
        }

        $this->assertAuthenticatedAs($user);
    }

    public function test_logout_endpoint_redirects_to_login_after_a_valid_csrf_request(): void
    {
        $user = User::factory()->create();

        $this->withSession(['_token' => 'csrf-token'])
            ->actingAs($user)
            ->post(route('logout'), ['_token' => 'csrf-token'])
            ->assertRedirectToRoute('login')
            ->assertSessionHas('pwa_logout_confirmed', true);

        $this->assertGuest();
    }

    public function test_session_fresh_alias_expires_an_authenticated_session_on_the_citizen_dashboard(): void
    {
        CarbonImmutable::setTestNow('2026-07-31 10:00:00');
        $user = User::factory()->create();
        $this->grant($user, 'warga', 'profile.view');

        $this->withSession([
            EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(30)->getTimestamp(),
        ])
            ->actingAs($user)
            ->get(route('citizen.dashboard'))
            ->assertRedirectToRoute('login')
            ->assertSessionHas('pwa_logout_confirmed', true);

        $this->assertGuest();
        $audit = AuditLog::query()->sole();

        self::assertSame('access.session.idle_expired', $audit->getAttribute('action'));
        self::assertSame($user->id, $audit->getAttribute('actor_id'));
    }

    public function test_login_form_renders_accessible_autocomplete_and_generic_error_contracts(): void
    {
        config()->set('app.demo_mode', true);

        Livewire::test(LoginForm::class)
            ->assertSeeHtml('id="login-title"')
            ->assertSeeHtml('autocomplete="username"')
            ->assertSeeHtml('autocomplete="current-password"')
            ->assertSeeHtml('wire:submit="login"')
            ->assertSeeHtml('x-data="{ showPassword: false }"')
            ->assertSeeHtml('x-bind:aria-label="showPassword ? \'Sembunyikan kata sandi\' : \'Tampilkan kata sandi\'"')
            ->assertSee('Akses Akun Layanan')
            ->assertSee('layanan sesuai peran Anda')
            ->assertSee('Halo! Yuk masuk')
            ->assertSee('Petugas')
            ->assertSee('Bendahara')
            ->assertDontSee('Akses Akun Warga');
    }

    public function test_login_error_summary_links_to_invalid_fields_and_focuses_after_livewire_validation(): void
    {
        Livewire::test(LoginForm::class)
            ->set('phone', '')
            ->set('password', '')
            ->call('login')
            ->assertHasErrors(['phone', 'password'])
            ->assertSeeHtml('id="login-errors"')
            ->assertSeeHtml('href="#phone"')
            ->assertSeeHtml('href="#password"')
            ->assertSeeHtml('tabindex="-1"')
            ->assertSeeHtml('x-on:login-invalid.window="$nextTick(() => $el.focus())"')
            ->assertSeeHtml('aria-describedby="login-errors"');
    }

    public function test_login_heading_keeps_desktop_heading_size_token(): void
    {
        $html = $this->get(route('login'))->assertOk()->getContent();

        self::assertMatchesRegularExpression(
            '/<h1\\b(?=[^>]*\\bid="login-title")(?=[^>]*\\blg:text-h1\\b)[^>]*>/s',
            $html,
        );
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
