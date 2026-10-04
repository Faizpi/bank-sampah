<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Actions\Auth\RevokeDatabaseSession;
use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Filament\Resources\Identity\Models\SessionInventories\Pages\ManageSessionInventories;
use App\Filament\Resources\Identity\Models\SessionInventories\SessionInventoryResource;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class SessionInventoryResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_administrator_lists_only_safe_session_metadata_and_revokes_the_selected_session(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $selectedSession = $this->createSession($target);
        $preservedSession = $this->createSession($target);
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'user.view.all', 'session.revoke');

        $this->actingAs($admin);

        self::assertTrue(SessionInventoryResource::canViewAny());
        $livewire = Livewire::test(ManageSessionInventories::class);

        $livewire
            ->assertCanSeeTableRecords([$selectedSession, $preservedSession])
            ->assertDontSee((string) $selectedSession->getKey())
            ->assertDontSee((string) $preservedSession->getKey())
            ->assertDontSee('private-payload')
            ->assertDontSee('127.0.0.1')
            ->assertDontSee('PHPUnit')
            ->assertTableActionVisible('revoke', $selectedSession)
            ->callTableAction('revoke', $selectedSession);

        $this->assertDatabaseMissing('sessions', ['id' => $selectedSession->getKey()]);
        $this->assertDatabaseHas('sessions', ['id' => $preservedSession->getKey()]);
        $this->assertSanitizedAudit($admin, $target);
    }

    public function test_user_view_without_explicit_area_or_all_scope_cannot_see_another_users_session(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $session = $this->createSession($target);
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'session.revoke');

        $this->actingAs($admin);

        self::assertFalse(SessionInventoryResource::canViewAny());
        self::assertFalse(SessionInventoryResource::getEloquentQuery()->whereKey($session->getKey())->exists());
    }

    public function test_area_scope_lists_only_own_and_in_area_active_customer_sessions(): void
    {
        [$admin, , $allowedRt] = $this->createStaffArea(today()->subDay()->toDateString(), null, true, true);
        $allowedTarget = User::factory()->create();
        $allowedTarget->customerProfile()->create($this->customerProfileAttributes($allowedRt));
        $outsideTarget = User::factory()->create();
        $outsideTarget->customerProfile()->create($this->customerProfileAttributes($this->createRt('RT-OUTSIDE')));
        $allowedSession = $this->createSession($allowedTarget);
        $outsideSession = $this->createSession($outsideTarget);
        $this->grant($admin, 'area-admin', 'backoffice.access', 'user.view', 'user.view.area', 'session.revoke');

        $this->actingAs($admin);

        Livewire::test(ManageSessionInventories::class)
            ->assertCanSeeTableRecords([$allowedSession])
            ->assertCanNotSeeTableRecords([$outsideSession])
            ->assertTableActionVisible('revoke', $allowedSession);
    }

    public function test_all_scope_excludes_inactive_target_sessions_and_their_revoke_action(): void
    {
        $admin = User::factory()->create();
        $inactiveTarget = User::factory()->inactive()->create();
        $inactiveSession = $this->createSession($inactiveTarget);
        $this->grant($admin, 'all-admin', 'backoffice.access', 'user.view', 'user.view.all', 'session.revoke');

        $this->actingAs($admin);

        Livewire::test(ManageSessionInventories::class)
            ->assertCanNotSeeTableRecords([$inactiveSession]);
    }

    public function test_self_owned_session_is_visible_but_revoke_action_is_hidden(): void
    {
        $admin = User::factory()->create();
        $session = $this->createSession($admin);
        $this->grant($admin, 'self-admin', 'backoffice.access', 'user.view', 'user.view.all', 'session.revoke');

        $this->actingAs($admin);

        Livewire::test(ManageSessionInventories::class)
            ->assertCanSeeTableRecords([$session])
            ->assertTableActionHidden('revoke', $session);
    }

    public function test_cross_owner_guessed_session_id_is_not_deleted_for_a_visible_authorized_target(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();
        $otherSession = $this->createSession($otherTarget);
        $this->grant($admin, 'owner-admin', 'backoffice.access', 'user.view', 'user.view.all', 'session.revoke');

        $this->expectException(ModelNotFoundException::class);

        try {
            app(RevokeDatabaseSession::class)->handle($admin, $target, (string) $otherSession->getKey(), (string) Str::uuid());
        } finally {
            $this->assertDatabaseHas('sessions', ['id' => $otherSession->getKey(), 'user_id' => $otherTarget->id]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.session.revoked']);
        }
    }

    public function test_missing_user_view_cannot_list_or_revoke_sessions(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $session = $this->createSession($target);
        $this->grant($admin, 'admin', 'backoffice.access', 'session.revoke');

        $this->actingAs($admin);

        self::assertFalse(SessionInventoryResource::canViewAny());
        $this->expectException(AuthorizationException::class);

        try {
            app(RevokeDatabaseSession::class)->handle($admin, $target, (string) $session->getKey(), (string) Str::uuid());
        } finally {
            $this->assertDatabaseHas('sessions', ['id' => $session->getKey()]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.session.revoked']);
        }
    }

    public function test_missing_session_revoke_cannot_revoke_a_visible_target_session(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $session = $this->createSession($target);
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'user.view.all');

        $this->actingAs($admin);
        Livewire::test(ManageSessionInventories::class)
            ->assertCanSeeTableRecords([$session])
            ->assertTableActionHidden('revoke', $session);

        $this->expectException(AuthorizationException::class);

        try {
            app(RevokeDatabaseSession::class)->handle($admin, $target, (string) $session->getKey(), (string) Str::uuid());
        } finally {
            $this->assertDatabaseHas('sessions', ['id' => $session->getKey()]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.session.revoked']);
        }
    }

    public function test_self_target_is_rejected_without_mutating_the_session(): void
    {
        $admin = User::factory()->create();
        $session = $this->createSession($admin);
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'session.revoke');

        $this->expectException(AuthorizationException::class);

        try {
            app(RevokeDatabaseSession::class)->handle($admin, $admin, (string) $session->getKey(), (string) Str::uuid());
        } finally {
            $this->assertDatabaseHas('sessions', ['id' => $session->getKey()]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.session.revoked']);
        }
    }

    public function test_cross_owner_guessed_session_id_is_not_deleted(): void
    {
        $admin = User::factory()->create();
        $target = User::factory()->create();
        $otherTarget = User::factory()->create();
        $otherSession = $this->createSession($otherTarget);
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'session.revoke');

        $this->expectException(AuthorizationException::class);

        try {
            app(RevokeDatabaseSession::class)->handle($admin, $target, (string) $otherSession->getKey(), (string) Str::uuid());
        } finally {
            $this->assertDatabaseHas('sessions', ['id' => $otherSession->getKey(), 'user_id' => $otherTarget->id]);
            $this->assertDatabaseMissing('audit_logs', ['action' => 'identity.session.revoked']);
        }
    }

    /** @return array{0: User, 1: ServiceArea, 2: Rt} */
    private function createStaffArea(string $activeFrom, ?string $activeTo, bool $areaActive, bool $rtActive): array
    {
        $admin = User::factory()->create();
        $area = ServiceArea::query()->create(['name' => 'Area '.$admin->id, 'is_active' => $areaActive]);
        $rt = $this->createRt('RT-ALLOWED-'.$admin->id, $rtActive);
        $this->runRegionMutation(fn (): array => $area->rts()->sync([$rt->id]));
        StaffProfile::query()->create([
            'user_id' => $admin->id,
            'staff_number' => 'STF-'.str_pad((string) $admin->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => $activeFrom,
            'active_to' => $activeTo,
        ]);

        return [$admin, $area, $rt];
    }

    private function createRt(string $code, bool $active = true): Rt
    {
        $dusun = Dusun::query()->create(['code' => $code.'-D', 'name' => $code.' Dusun']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code.'-W', 'name' => $code.' RW']);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $code, 'is_active' => $active]);
    }

    /** @return array{rt_id: int, address: string, joined_at: Carbon} */
    private function customerProfileAttributes(Rt $rt): array
    {
        return ['rt_id' => $rt->id, 'address' => 'Alamat uji', 'joined_at' => today()];
    }

    private function runRegionMutation(callable $callback): mixed
    {
        return RegionMutationGuard::run($callback);
    }

    private function createSession(User $user): object
    {
        $id = (string) Str::uuid();
        $this->app['db']->table('sessions')->insert([
            'id' => $id,
            'user_id' => $user->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'private-payload',
            'last_activity' => now()->timestamp,
            'expires_at' => now()->addHour(),
        ]);

        return $user->databaseSessions()->findOrFail($id);
    }

    private function assertSanitizedAudit(User $actor, User $target): void
    {
        /** @var AuditLog $audit */
        $audit = AuditLog::query()
            ->where('actor_id', $actor->id)
            ->where('auditable_id', $target->id)
            ->where('action', 'identity.session.revoked')
            ->sole();

        self::assertSame(['target_session_revoked' => true], $audit->new_values);
        self::assertSame([], $audit->old_values);
        self::assertArrayNotHasKey('id', $audit->new_values);
        self::assertArrayNotHasKey('payload', $audit->new_values);
        self::assertArrayNotHasKey('ip_address', $audit->new_values);
        self::assertArrayNotHasKey('ip_address_hash', $audit->new_values);
        self::assertArrayNotHasKey('user_agent', $audit->new_values);
        self::assertArrayNotHasKey('cookie', $audit->new_values);
        self::assertArrayNotHasKey('token', $audit->new_values);
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
