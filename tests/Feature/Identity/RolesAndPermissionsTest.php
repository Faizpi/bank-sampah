<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Actions\ManageRoles;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RolesAndPermissionsTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const PERMISSIONS = [
        'announcement.manage', 'announcement.publish', 'announcement.view', 'audit.retention.execute', 'audit.view',
        'backoffice.access', 'backup.restore', 'backup.run', 'backup.view', 'correction.view-customer', 'customer.card.issue',
        'customer.create-assisted', 'customer.qr.rotate', 'customer.update', 'customer.view', 'deposit.approve', 'deposit.create',
        'deposit.finalize', 'deposit.update-draft', 'deposit.view', 'grocery.approve', 'grocery.cancel',
        'grocery.handover', 'grocery.package.manage', 'grocery.package.view', 'grocery.prepare', 'grocery.request',
        'grocery.view', 'ledger.adjust', 'ledger.view', 'media.retention.execute', 'mobile-service.manage', 'mobile-service.operate',
        'mobile-service.view', 'notification.view', 'pickup.cancel', 'pickup.capacity.manage', 'pickup.complete',
        'pickup.execute', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.view', 'price.manage',
        'price.view', 'profile.update', 'profile.view', 'qr-verification.rotate', 'reconciliation.approve', 'reconciliation.create',
        'reconciliation.view', 'region.manage', 'region.view', 'report.export', 'report.view',
        'role.manage', 'role.view', 'session.revoke', 'statistics.internal.view', 'statistics.public.manage',
        'system.maintenance', 'system.settings.manage', 'target.manage', 'target.publish', 'target.view',
        'transaction.correct', 'transaction.reverse', 'user.activate', 'user.create', 'user.reject',
        'user.reset-password', 'user.update', 'user.verify', 'user.view', 'user.view.all', 'user.view.area', 'waste.manage', 'waste.view',
        'withdrawal.approve', 'withdrawal.cancel', 'withdrawal.pay', 'withdrawal.request', 'withdrawal.view',
    ];

    /** @var array<string, list<string>> */
    private const ROLE_PERMISSIONS = [
        'warga' => ['profile.view', 'profile.update', 'customer.view', 'customer.update', 'region.view', 'waste.view', 'price.view', 'deposit.view', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.cancel', 'withdrawal.request', 'withdrawal.view', 'withdrawal.cancel', 'grocery.package.view', 'grocery.request', 'grocery.view', 'grocery.cancel', 'notification.view', 'announcement.view', 'mobile-service.view', 'target.view'],
        'petugas' => ['profile.view', 'profile.update', 'user.view', 'user.view.area', 'customer.view', 'customer.create-assisted', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.view', 'waste.view', 'price.view', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'withdrawal.request', 'withdrawal.view', 'withdrawal.pay', 'grocery.package.view', 'grocery.view', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'notification.view', 'announcement.view', 'mobile-service.view', 'mobile-service.operate', 'target.view', 'statistics.internal.view', 'report.view'],
        'bendahara' => ['profile.view', 'profile.update', 'user.view', 'user.view.area', 'customer.view', 'region.view', 'waste.view', 'price.view', 'ledger.view', 'correction.view-customer', 'withdrawal.request', 'withdrawal.view', 'withdrawal.pay', 'withdrawal.cancel', 'grocery.package.view', 'notification.view', 'announcement.view', 'mobile-service.view', 'target.view', 'statistics.internal.view', 'report.view', 'report.export'],
        'admin' => ['profile.view', 'profile.update', 'backoffice.access', 'user.view', 'user.view.all', 'user.create', 'user.update', 'user.activate', 'user.verify', 'user.reject', 'user.reset-password', 'role.view', 'session.revoke', 'customer.view', 'customer.create-assisted', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.view', 'region.manage', 'waste.view', 'waste.manage', 'price.view', 'price.manage', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'ledger.view', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'pickup.capacity.manage', 'withdrawal.request', 'withdrawal.view', 'withdrawal.approve', 'withdrawal.pay', 'withdrawal.cancel', 'grocery.package.view', 'grocery.package.manage', 'grocery.view', 'grocery.approve', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'notification.view', 'announcement.view', 'announcement.manage', 'announcement.publish', 'mobile-service.view', 'mobile-service.manage', 'mobile-service.operate', 'target.view', 'target.manage', 'target.publish', 'statistics.internal.view', 'statistics.public.manage', 'qr-verification.rotate', 'report.view', 'report.export', 'audit.view'],
        'superadmin' => ['profile.view', 'profile.update', 'backoffice.access', 'user.view', 'user.view.all', 'user.create', 'user.update', 'user.activate', 'user.verify', 'user.reject', 'user.reset-password', 'role.view', 'role.manage', 'session.revoke', 'customer.view', 'customer.create-assisted', 'customer.update', 'customer.card.issue', 'customer.qr.rotate', 'region.view', 'region.manage', 'waste.view', 'waste.manage', 'price.view', 'price.manage', 'deposit.view', 'deposit.create', 'deposit.update-draft', 'deposit.finalize', 'deposit.approve', 'ledger.view', 'transaction.correct', 'transaction.reverse', 'ledger.adjust', 'correction.view-customer', 'pickup.view', 'pickup.request', 'pickup.review', 'pickup.schedule', 'pickup.execute', 'pickup.complete', 'pickup.cancel', 'pickup.capacity.manage', 'withdrawal.request', 'withdrawal.view', 'withdrawal.approve', 'withdrawal.pay', 'withdrawal.cancel', 'grocery.package.view', 'grocery.package.manage', 'grocery.view', 'grocery.approve', 'grocery.prepare', 'grocery.handover', 'grocery.cancel', 'notification.view', 'announcement.view', 'announcement.manage', 'announcement.publish', 'mobile-service.view', 'mobile-service.manage', 'mobile-service.operate', 'target.view', 'target.manage', 'target.publish', 'statistics.internal.view', 'statistics.public.manage', 'qr-verification.rotate', 'report.view', 'report.export', 'audit.view', 'reconciliation.view', 'reconciliation.create', 'reconciliation.approve', 'system.settings.manage', 'system.maintenance', 'backup.run', 'backup.view', 'backup.restore', 'audit.retention.execute', 'media.retention.execute'],
    ];

    public function test_schema_has_documented_catalog_and_assignment_metadata(): void
    {
        self::assertTrue(Schema::hasColumns('roles', ['id', 'name', 'description', 'created_at', 'updated_at']));
        self::assertTrue(Schema::hasColumns('permissions', ['id', 'name', 'description', 'created_at', 'updated_at']));
        self::assertTrue(Schema::hasColumns('role_user', ['role_id', 'user_id', 'assigned_by', 'reason', 'created_at', 'updated_at']));
        self::assertTrue(Schema::hasColumns('permission_role', ['permission_id', 'role_id', 'granted_by', 'reason', 'created_at', 'updated_at']));
    }

    public function test_role_names_are_unique(): void
    {
        Role::factory()->create(['name' => 'warga']);
        $this->expectException(QueryException::class);
        Role::factory()->create(['name' => 'warga']);
    }

    public function test_permission_names_are_unique(): void
    {
        Permission::factory()->create(['name' => 'profile.view']);
        $this->expectException(QueryException::class);
        Permission::factory()->create(['name' => 'profile.view']);
    }

    public function test_role_user_pairs_are_unique(): void
    {
        $role = Role::factory()->create();
        $user = User::factory()->create();
        DB::table('role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);
        $this->expectException(QueryException::class);
        DB::table('role_user')->insert(['role_id' => $role->id, 'user_id' => $user->id]);
    }

    public function test_permission_role_pairs_are_unique(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $permission->id]);
        $this->expectException(QueryException::class);
        DB::table('permission_role')->insert(['role_id' => $role->id, 'permission_id' => $permission->id]);
    }

    public function test_all_pivot_foreign_keys_reject_missing_references(): void
    {
        $rejectedReferences = 0;

        foreach ([
            ['role_user', ['role_id' => 999_001, 'user_id' => User::factory()->create()->id]],
            ['role_user', ['role_id' => Role::factory()->create()->id, 'user_id' => 999_002]],
            ['role_user', ['role_id' => Role::factory()->create()->id, 'user_id' => User::factory()->create()->id, 'assigned_by' => 999_003]],
            ['permission_role', ['permission_id' => 999_004, 'role_id' => Role::factory()->create()->id]],
            ['permission_role', ['permission_id' => Permission::factory()->create()->id, 'role_id' => 999_005]],
            ['permission_role', ['permission_id' => Permission::factory()->create()->id, 'role_id' => Role::factory()->create()->id, 'granted_by' => 999_006]],
        ] as [$table, $values]) {
            try {
                DB::table($table)->insert($values);
                self::fail("{$table} accepted a missing foreign key reference.");
            } catch (QueryException) {
                $rejectedReferences++;
            }
        }

        self::assertSame(6, $rejectedReferences);
    }

    public function test_actor_deletion_is_restricted(): void
    {
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $role->users()->attach($assignee, ['assigned_by' => $actor->id]);
        $role->permissions()->attach($permission, ['granted_by' => $actor->id]);

        $this->expectException(QueryException::class);
        $actor->forceDelete();
    }

    public function test_reciprocal_relationships_preserve_assignment_metadata_and_timestamps(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $user = User::factory()->create();
        $actor = User::factory()->create();
        $role->permissions()->attach($permission, ['granted_by' => $actor->id, 'reason' => 'Baseline approved.']);
        $user->roles()->attach($role, ['assigned_by' => $actor->id, 'reason' => 'Assignment approved.']);

        self::assertTrue($user->roles()->sole()->is($role));
        self::assertTrue($permission->roles()->sole()->is($role));
        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $user->id,
            'assigned_by' => $actor->id,
            'reason' => 'Assignment approved.',
        ]);
        $this->assertDatabaseHas('permission_role', [
            'role_id' => $role->id,
            'permission_id' => $permission->id,
            'granted_by' => $actor->id,
            'reason' => 'Baseline approved.',
        ]);
        self::assertNotNull(DB::table('role_user')->value('created_at'));
        self::assertNotNull(DB::table('role_user')->value('updated_at'));
        self::assertNotNull(DB::table('permission_role')->value('created_at'));
        self::assertNotNull(DB::table('permission_role')->value('updated_at'));
    }

    public function test_role_user_and_permission_deletions_cascade_pivots(): void
    {
        $role = Role::factory()->create();
        $permission = Permission::factory()->create();
        $user = User::factory()->create();
        $role->permissions()->attach($permission);
        $role->users()->attach($user);

        $permission->delete();
        self::assertSame(0, DB::table('permission_role')->count());
        $user->forceDelete();
        self::assertSame(0, DB::table('role_user')->count());
    }

    public function test_seeded_catalog_and_role_matrix_exactly_match_documentation_and_are_idempotent(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
        $this->seed(RolesAndPermissionsSeeder::class);

        self::assertSame(self::PERMISSIONS, Permission::query()->orderBy('name')->pluck('name')->all());
        self::assertSame(array_keys(self::ROLE_PERMISSIONS), Role::query()->orderBy('id')->pluck('name')->all());

        foreach (self::ROLE_PERMISSIONS as $role => $expectedPermissions) {
            sort($expectedPermissions);
            $actualPermissions = Role::query()->where('name', $role)->sole()->permissions()->orderBy('name')->pluck('name')->all();
            self::assertSame($expectedPermissions, $actualPermissions, "Unexpected permission matrix for {$role}.");
        }

        self::assertSame(array_sum(array_map('count', self::ROLE_PERMISSIONS)), DB::table('permission_role')->count());
    }

    public function test_superadmin_permissions_are_exactly_the_admin_union_with_technical_and_reconciliation_permissions(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminPermissions = Role::query()->where('name', 'admin')->sole()->permissions()->pluck('name')->sort()->values()->all();
        $technicalPermissions = [
            'role.manage',
            'reconciliation.view',
            'reconciliation.create',
            'reconciliation.approve',
            'system.settings.manage',
            'system.maintenance',
            'backup.run',
            'backup.view',
            'backup.restore',
            'audit.retention.execute',
            'media.retention.execute',
        ];
        $reconciliationPermissions = ['deposit.approve', 'transaction.correct', 'transaction.reverse', 'ledger.adjust'];
        $expected = collect([...$adminPermissions, ...$technicalPermissions, ...$reconciliationPermissions])->unique()->sort()->values()->all();
        $actual = Role::query()->where('name', 'superadmin')->sole()->permissions()->pluck('name')->sort()->values()->all();

        self::assertSame($expected, $actual);
        self::assertTrue(Role::query()->where('name', 'superadmin')->sole()->permissions()->whereIn('name', $reconciliationPermissions)->count() === 4);
    }

    public function test_role_create_and_update_roll_back_role_and_permission_changes_atomically(): void
    {
        $actor = User::factory()->create();
        $roleManage = Permission::factory()->create(['name' => 'role.manage']);
        $existingPermission = Permission::factory()->create(['name' => 'existing.permission']);
        $actorRole = Role::factory()->create(['name' => 'role-manager']);
        $actorRole->permissions()->attach($roleManage);
        $actor->roles()->attach($actorRole);

        try {
            app(ManageRoles::class)->createRole($actor->fresh(), 'atomic-create', 'Tidak boleh tersisa.', [$existingPermission->id, 999999]);
            self::fail('Invalid permission IDs must abort role creation.');
        } catch (ValidationException) {
            self::assertDatabaseMissing('roles', ['name' => 'atomic-create']);
            self::assertDatabaseMissing('permission_role', ['role_id' => Role::query()->where('name', 'atomic-create')->value('id')]);
        }

        $role = Role::factory()->create(['name' => 'atomic-update', 'description' => 'Deskripsi lama.']);
        $role->permissions()->attach($existingPermission);

        try {
            app(ManageRoles::class)->updateRole($actor->fresh(), $role->fresh(), 'Deskripsi baru.', [$roleManage->id, 999998]);
            self::fail('Invalid permission IDs must abort role updates.');
        } catch (ValidationException) {
            $role->refresh();
            self::assertSame('Deskripsi lama.', $role->description);
            self::assertSame([$existingPermission->id], $role->permissions()->pluck('permissions.id')->all());
        }
    }

    public function test_superadmin_inherits_every_admin_permission_and_adds_technical_access(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        $adminPermissions = Role::query()->where('name', 'admin')->sole()->permissions()->pluck('permissions.name')->all();
        $superadminPermissions = Role::query()->where('name', 'superadmin')->sole()->permissions()->pluck('permissions.name')->all();

        self::assertSame([], array_diff($adminPermissions, $superadminPermissions));
        self::assertContains('role.manage', $superadminPermissions);
        self::assertContains('system.settings.manage', $superadminPermissions);
        self::assertContains('backup.restore', $superadminPermissions);
    }

    public function test_migrations_reverse_and_restore_the_schema_safely_on_sqlite(): void
    {
        $pivotMigration = require database_path('migrations/2026_07_30_120500_create_role_user_and_permission_role_tables.php');
        $catalogMigration = require database_path('migrations/2026_07_30_120400_create_roles_and_permissions_tables.php');

        $pivotMigration->down();
        $catalogMigration->down();
        self::assertFalse(Schema::hasTable('permission_role'));
        self::assertFalse(Schema::hasTable('role_user'));
        self::assertFalse(Schema::hasTable('permissions'));
        self::assertFalse(Schema::hasTable('roles'));

        $catalogMigration->up();
        $pivotMigration->up();
        self::assertTrue(Schema::hasTable('roles'));
        self::assertTrue(Schema::hasTable('permissions'));
        self::assertTrue(Schema::hasTable('role_user'));
        self::assertTrue(Schema::hasTable('permission_role'));
    }
}
