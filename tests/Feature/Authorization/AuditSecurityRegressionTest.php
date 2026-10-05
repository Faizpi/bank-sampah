<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Actions\Auth\ChangePassword;
use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Actions\ManageRoles;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Models\Role;
use App\Filament\Pages\TechnicalHealthPage;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Http\Middleware\RequirePermission;
use App\Models\User;
use App\Support\Auth\SessionAccess;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Drawer\Utils;
use Livewire\Livewire;
use Livewire\Mechanisms\PersistentMiddleware\PersistentMiddleware;
use Tests\TestCase;

final class AuditSecurityRegressionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    public function test_admin_cannot_create_a_superadmin_but_can_create_an_operational_account(): void
    {
        $admin = $this->actor('admin');
        self::assertFalse(app(PermissionChecker::class)->allows($admin, 'role.manage'));
        $data = ['name' => 'Akun uji', 'username' => 'akun.uji', 'password' => 'Testing#1234', 'password_confirmation' => 'Testing#1234'];
        $operator = app(ManageUsers::class)->create($admin, $data + ['role_id' => Role::where('name', 'petugas')->value('id')]);
        self::assertTrue($operator->hasRole('petugas'));
        $this->expectException(AuthorizationException::class);
        app(ManageUsers::class)->create($admin, array_replace($data, ['username' => 'forbidden.super', 'role_id' => Role::where('name', 'superadmin')->value('id')]));
    }

    public function test_admin_cannot_reset_or_mutate_a_superadmin_account(): void
    {
        $admin = $this->actor('admin');
        $super = $this->actor('superadmin');
        foreach (['resetPassword', 'revokeSession', 'update', 'manageUpdate', 'deactivate'] as $ability) {
            self::assertFalse(Gate::forUser($admin)->allows($ability, $super));
        }
        $oldPassword = $super->password;
        try {
            app(ChangePassword::class)->directAdmin($admin, $super, 'tatap_muka', 'Verifikasi akun untuk pengujian.', 'Changed#1234', 'Changed#1234', (string) Str::uuid());
            self::fail('Admin must not reset superadmin credentials.');
        } catch (AuthorizationException) {
            self::assertSame($oldPassword, $super->fresh()->password);
        }
    }

    public function test_permission_edits_record_the_removed_grants(): void
    {
        $super = $this->actor('superadmin');
        $role = Role::where('name', 'petugas')->firstOrFail();
        $oldIds = $role->permissions()->pluck('permissions.id')->all();
        $newIds = array_slice($oldIds, 1);
        app(ManageRoles::class)->updateRole($super, $role, 'Peran operasional', $newIds, 'Penyesuaian tugas lapangan resmi.');
        $audit = AuditLog::where('action', 'identity.role.permissions_updated')->sole();
        self::assertSame($oldIds, $audit->old_values['permission_ids']);
        self::assertSame($newIds, $audit->new_values['permission_ids']);
    }

    public function test_health_exposes_no_inherited_operational_mutations(): void
    {
        $methods = Utils::getPublicMethodsDefinedBySubClass(new TechnicalHealthPage);
        foreach (['toggleMaintenance', 'saveSettings', 'executeRetention', 'executeMediaRetention', 'requestBackup'] as $method) {
            self::assertNotContains($method, $methods);
        }
    }

    public function test_permission_and_session_guards_are_persistent(): void
    {
        $middleware = app(PersistentMiddleware::class)->getPersistentMiddleware();
        self::assertContains(RequirePermission::class, $middleware);
        self::assertContains(EnsureSessionIsFresh::class, $middleware);
    }

    public function test_backoffice_expires_idle_and_absolute_sessions(): void
    {
        $super = $this->actor('superadmin');
        $this->actingAs($super)->withSession([EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(31)->timestamp])
            ->get('/backoffice')->assertRedirectToRoute('login');
        $this->actingAs($super)->withSession([
            EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->timestamp,
            SessionAccess::STARTED_AT => now()->subHours(8)->timestamp,
        ])->get('/backoffice')->assertRedirectToRoute('login');
    }

    public function test_permission_changes_invalidate_an_existing_session(): void
    {
        $citizen = $this->actor('warga');
        $fingerprint = SessionAccess::fingerprint($citizen);
        $citizen->roles()->detach();
        $this->actingAs($citizen)->withSession([SessionAccess::AUTHORIZATION => $fingerprint])
            ->get(route('citizen.dashboard'))->assertRedirectToRoute('login');
        $this->assertGuest();
    }

    private function actor(string $role): User
    {
        $user = User::factory()->create();
        $user->roles()->attach(Role::where('name', $role)->firstOrFail());

        return $user;
    }

    public function test_real_livewire_update_rechecks_the_original_route_permission(): void
    {
        $citizen = $this->actor('warga');
        $html = $this->actingAs($citizen)->get(route('citizen.deposit-history'))->assertOk()->getContent();
        self::assertSame(1, preg_match('/wire:snapshot="([^"]+)"/', $html, $match));
        $snapshot = html_entity_decode($match[1], ENT_QUOTES);
        $citizen->roles()->detach();
        // Simulate a fresh session without the old fingerprint to specifically exercise the route permission guard.
        session()->forget(SessionAccess::AUTHORIZATION);
        $response = $this->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => [['path' => '', 'method' => '$refresh', 'params' => []]]]],
        ], ['X-Livewire' => 'true']);
        $response->assertForbidden();
    }
}
