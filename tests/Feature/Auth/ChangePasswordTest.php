<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\ChangePassword;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Livewire\Profile\Password as ProfilePassword;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class ChangePasswordTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_service_changes_password_preserves_current_database_session_and_revokes_others(): void
    {
        $user = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->grant($user, 'warga', 'profile.view', 'profile.update');
        $currentSessionId = (string) Str::uuid();
        $otherSessionId = (string) Str::uuid();
        $this->createSession($user, $currentSessionId);
        $this->createSession($user, $otherSessionId);

        app(ChangePassword::class)->selfService(
            $user->fresh(),
            'kata-sandi-lama',
            'kata-sandi-baru-yang-kuat',
            'kata-sandi-baru-yang-kuat',
            $currentSessionId,
            (string) Str::uuid(),
        );

        self::assertTrue(Hash::check('kata-sandi-baru-yang-kuat', $user->fresh()->password));
        $this->assertDatabaseHas('sessions', ['id' => $currentSessionId]);
        $this->assertDatabaseMissing('sessions', ['id' => $otherSessionId]);
        $audit = $this->assertAudit($user, $user, 'identity.password.changed.self_service');
        self::assertSame('mandiri_profil', $audit->new_values['verification_method']);
        self::assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_self_service_rejects_incorrect_current_password_without_mutations(): void
    {
        $user = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->grant($user, 'warga', 'profile.update');
        $sessionId = (string) Str::uuid();
        $this->createSession($user, $sessionId);

        $this->expectException(ValidationException::class);

        try {
            app(ChangePassword::class)->selfService(
                $user->fresh(),
                'keliru-sekali',
                'kata-sandi-baru-yang-kuat',
                'kata-sandi-baru-yang-kuat',
                $sessionId,
                (string) Str::uuid(),
            );
        } finally {
            self::assertTrue(Hash::check('kata-sandi-lama', $user->fresh()->password));
            $this->assertDatabaseHas('sessions', ['id' => $sessionId]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.password.changed.self_service']);
        }
    }

    public function test_direct_admin_changes_other_user_password_revokes_all_sessions_and_audits_sanitized_data(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->grant($admin, 'admin', 'user.view', 'user.view.all', 'user.reset-password', 'session.revoke');
        $this->createSession($target, (string) Str::uuid());
        $this->createSession($target, (string) Str::uuid());

        app(ChangePassword::class)->directAdmin(
            $admin->fresh(),
            $target,
            'tatap_muka',
            'Warga hadir langsung dan identitasnya sudah diperiksa.',
            'kata-sandi-baru-yang-kuat',
            'kata-sandi-baru-yang-kuat',
            (string) Str::uuid(),
        );

        self::assertTrue(Hash::check('kata-sandi-baru-yang-kuat', $target->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['user_id' => $target->id]);
        $audit = $this->assertAudit($admin, $target, 'identity.password.changed.direct_admin');
        self::assertSame('tatap_muka', $audit->new_values['verification_method']);
        self::assertMatchesRegularExpression('/^\[REDACTED: \d+ karakter\]$/', $audit->new_values['reason']);
        self::assertArrayNotHasKey('password', $audit->new_values);
    }

    public function test_direct_admin_rejects_self_target_unprivileged_inactive_and_invalid_requests_without_mutations(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->createSession($target, (string) Str::uuid());

        foreach ([
            [$admin, $admin, 'tatap_muka', 'Alasan yang cukup panjang.'],
            [$admin, $target, 'tatap_muka', 'Alasan yang cukup panjang.'],
            [User::factory()->inactive()->create(), $target, 'tatap_muka', 'Alasan yang cukup panjang.'],
            [$admin, $target, 'email', 'Alasan yang cukup panjang.'],
            [$admin, $target, 'tatap_muka', 'singkat'],
        ] as [$actor, $subject, $method, $reason]) {
            try {
                app(ChangePassword::class)->directAdmin(
                    $actor,
                    $subject,
                    $method,
                    $reason,
                    'kata-sandi-baru-yang-kuat',
                    'kata-sandi-baru-yang-kuat',
                    (string) Str::uuid(),
                );
                self::fail('Permintaan tidak sah seharusnya ditolak.');
            } catch (AuthorizationException|ValidationException) {
                // Expected: no mutable effect is allowed before all guards pass.
            }
        }

        self::assertTrue(Hash::check('kata-sandi-lama', $target->fresh()->password));
        $this->assertDatabaseHas('sessions', ['user_id' => $target->id]);
        $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.password.changed.direct_admin']);
    }

    public function test_profile_route_admits_each_authenticated_role_with_profile_permissions(): void
    {
        foreach (['warga', 'petugas', 'bendahara'] as $roleName) {
            $user = User::factory()->create();
            $this->grant($user, $roleName, 'profile.view', 'profile.update');

            $this->actingAs($user)
                ->get(route('profile.password'))
                ->assertOk();
        }
    }

    public function test_profile_page_uses_citizen_shell_for_citizens_and_officer_shell_for_staff_and_backoffice_roles(): void
    {
        $citizen = User::factory()->create();
        $officer = User::factory()->create();
        $treasurer = User::factory()->create();
        $admin = User::factory()->create();
        $this->grant($citizen, 'warga', 'profile.view', 'profile.update');
        $this->grant($officer, 'petugas', 'profile.view', 'profile.update');
        $this->grant($treasurer, 'bendahara', 'profile.view', 'profile.update');
        $this->grant($admin, 'admin', 'profile.view', 'profile.update');

        $this->actingAs($citizen)->get(route('profile.password'))
            ->assertOk()
            ->assertSee('max-w-citizen')
            ->assertSee('Keamanan akun');

        foreach ([$officer, $treasurer, $admin] as $staffActor) {
            $this->actingAs($staffActor)->get(route('profile.password'))
                ->assertOk()
                ->assertSee('max-w-officer')
                ->assertDontSee('Keamanan akun');
        }
    }

    public function test_profile_ui_requires_current_password_and_does_not_change_it_when_wrong(): void
    {
        $user = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->grant($user, 'warga', 'profile.view', 'profile.update');

        $this->actingAs($user);

        Livewire::test(ProfilePassword::class)
            ->set('current_password', 'keliru-sekali')
            ->set('password', 'kata-sandi-baru-yang-kuat')
            ->set('password_confirmation', 'kata-sandi-baru-yang-kuat')
            ->call('changePassword')
            ->assertHasErrors(['current_password']);

        self::assertTrue(Hash::check('kata-sandi-lama', $user->fresh()->password));
        $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.password.changed.self_service']);
    }

    private function createSession(User $user, string $id): void
    {
        $this->app['db']->table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
    }

    private function assertAudit(User $actor, User $target, string $action): AuditLog
    {
        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('actor_id', $actor->id)
            ->where('auditable_id', $target->id)
            ->where('action', $action)
            ->sole();

        return $audit;
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

        $user->roles()->syncWithoutDetaching($role);
    }
}
