<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Filament\Resources\Identity\Models\Permissions\PermissionResource;
use App\Filament\Resources\Identity\Models\Roles\RoleResource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

final class RolePermissionAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_with_only_role_view_cannot_create_update_or_delete_roles_or_permissions(): void
    {
        $viewer = $this->panelUser(['role.view']);

        self::assertTrue(Gate::forUser($viewer)->allows('viewAny', Role::class));
        self::assertTrue(Gate::forUser($viewer)->allows('viewAny', Permission::class));

        self::assertFalse(Gate::forUser($viewer)->allows('create', Role::class));
        self::assertFalse(Gate::forUser($viewer)->allows('create', Permission::class));
        self::assertFalse(Gate::forUser($viewer)->allows('delete', Permission::factory()->create()));

        $role = Role::factory()->create(['name' => 'custom-role']);
        self::assertFalse(Gate::forUser($viewer)->allows('update', $role));
        self::assertFalse(Gate::forUser($viewer)->allows('delete', $role));

        self::assertFalse($viewer->can('create', Role::class));
        self::assertTrue(RoleResource::canViewAny());
        self::assertFalse(RoleResource::canCreate());
        self::assertFalse(PermissionResource::canCreate());
    }

    public function test_a_user_with_role_manage_can_create_and_edit_roles_but_not_permissions(): void
    {
        $manager = $this->panelUser(['role.view', 'role.manage']);

        self::assertTrue(Gate::forUser($manager)->allows('create', Role::class));
        self::assertFalse(Gate::forUser($manager)->allows('create', Permission::class));

        $role = Role::factory()->create(['name' => 'custom-role']);
        self::assertTrue(Gate::forUser($manager)->allows('update', $role));
        self::assertTrue(Gate::forUser($manager)->allows('delete', $role));

        self::assertTrue(RoleResource::canCreate());
        self::assertFalse(PermissionResource::canCreate());
        self::assertTrue(PermissionResource::canViewAny());
    }

    public function test_system_roles_cannot_be_deleted_even_by_a_role_manager(): void
    {
        $manager = $this->panelUser(['role.view', 'role.manage']);

        foreach (SystemRoles::NAMES as $systemRoleName) {
            $systemRole = Role::query()->firstOrCreate(
                ['name' => $systemRoleName],
                ['description' => "System role {$systemRoleName}"],
            );

            self::assertTrue(Gate::forUser($manager)->allows('update', $systemRole));
            self::assertFalse(Gate::forUser($manager)->allows('delete', $systemRole), "Expected {$systemRoleName} to be protected from deletion.");
            self::assertFalse(RoleResource::canDelete($systemRole));
        }
    }

    public function test_a_superadmin_can_view_and_manage_roles_using_the_seeded_permission_matrix(): void
    {
        $superadmin = $this->panelUser(['role.view', 'role.manage']);

        self::assertTrue(Gate::forUser($superadmin)->allows('viewAny', Role::class));
        self::assertTrue(Gate::forUser($superadmin)->allows('create', Role::class));
        self::assertTrue(Gate::forUser($superadmin)->allows('update', Role::factory()->create(['name' => 'custom-role'])));
        self::assertTrue(RoleResource::canViewAny());
        self::assertTrue(RoleResource::canCreate());

        // A superadmin still cannot mutate a permission (read-only catalog).
        self::assertFalse(PermissionResource::canCreate());
    }

    /** @param list<string> $permissionNames */
    private function panelUser(array $permissionNames): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['name' => 'panel-actor'],
            ['description' => 'Panel actor for authorization tests'],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);

        $this->actingAs($user);

        return $user;
    }
}
