<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Identity\Support\SystemRoles;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageRoles
{
    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
        private VisibleUsers $visibleUsers,
    ) {}

    /**
     * @param  list<int>  $permissionIds
     */
    public function createRole(User $actor, string $name, string $description, array $permissionIds): Role
    {
        $this->authorize($actor, 'create', Role::class);

        return DB::transaction(function () use ($actor, $name, $description, $permissionIds): Role {
            $role = Role::query()->create([
                'name' => $this->normalizedName($name),
                'description' => $description,
            ]);

            $this->syncPermissions($actor, $role, $permissionIds);

            return $role->fresh('permissions');
        });
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function updateRole(User $actor, Role $role, string $description, array $permissionIds): void
    {
        $this->authorize($actor, 'update', $role);

        DB::transaction(function () use ($actor, $role, $description, $permissionIds): void {
            $lockedRole = Role::query()->lockForUpdate()->findOrFail($role->getKey());
            $lockedRole->forceFill(['description' => $description])->save();
            $this->syncPermissions($actor, $lockedRole, $permissionIds);
        });
    }

    /** @param list<int> $roleIds */
    public function assignRoles(User $actor, User $subject, array $roleIds, string $reason): User
    {
        if (! $this->permissions->allows($actor, 'role.manage')) {
            throw new AuthorizationException('Anda tidak memiliki akses mengatur role.');
        }
        if ($actor->is($subject) || ! $this->visibleUsers->canView($actor, $subject, ...UserStatus::cases())) {
            throw new AuthorizationException('Pengguna berada di luar scope Anda.');
        }
        if (! Gate::forUser($actor)->allows('manageUpdate', $subject)) {
            throw new AuthorizationException('Anda tidak memiliki akses mengubah role pengguna.');
        }
        $reason = trim($reason);

        if (mb_strlen($reason) < 10 || mb_strlen($reason) > 1000) {
            throw ValidationException::withMessages(['reason' => 'Alasan assignment harus 10–1000 karakter.']);
        }

        $roleIds = array_values(array_unique(array_map('intval', $roleIds)));
        $roles = Role::query()->whereKey($roleIds)->get();
        if (count($roleIds) !== $roles->count()) {
            throw ValidationException::withMessages(['roles' => 'Satu atau lebih role tidak valid.']);
        }

        return DB::transaction(function () use ($actor, $subject, $roles, $roleIds, $reason): User {
            $locked = $this->visibleUsers->queryFor($actor, ...UserStatus::cases())
                ->whereKey($subject->getKey())
                ->lockForUpdate()
                ->first();

            if (! $locked instanceof User || $actor->is($locked)) {
                throw new AuthorizationException('Pengguna berada di luar scope Anda.');
            }

            $oldRoleIds = $locked->roles()->pluck('roles.id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
            $pivot = [];
            foreach ($roles as $role) {
                $pivot[$role->id] = ['assigned_by' => $actor->id, 'reason' => $reason];
            }

            $locked->roles()->sync($pivot);
            $this->auditLogger->record($actor, 'identity.user.roles_assigned', $locked, ['role_ids' => $oldRoleIds], ['role_ids' => $roleIds, 'reason' => $reason], $this->correlationId());

            return $locked->fresh('roles');
        });
    }

    public function deleteRole(User $actor, Role $role): void
    {
        $this->authorize($actor, 'delete', $role);

        if (SystemRoles::contains($role->name)) {
            throw ValidationException::withMessages([
                'name' => 'Role sistem tidak dapat dihapus.',
            ]);
        }

        $role->permissions()->detach();
        $role->delete();
    }

    /**
     * @param  list<int>  $permissionIds
     */
    private function syncPermissions(User $actor, Role $role, array $permissionIds): void
    {
        $permissionIds = array_values(array_unique($permissionIds));

        $existing = Permission::query()->whereKey($permissionIds)->get();
        if ($permissionIds !== [] && $existing->count() !== count($permissionIds)) {
            throw ValidationException::withMessages([
                'permissions' => 'Satu atau lebih permission terpilih tidak valid.',
            ]);
        }

        $pivot = [];
        foreach ($permissionIds as $permissionId) {
            $pivot[$permissionId] = [
                'granted_by' => $actor->id,
                'reason' => 'Dikelola melalui panel back-office.',
            ];
        }

        $role->permissions()->sync($pivot);
    }

    private function normalizedName(string $name): string
    {
        $trimmed = trim($name);

        if ($trimmed === '' || $trimmed !== $name) {
            throw ValidationException::withMessages(['name' => 'Nama role wajib diisi tanpa spasi di awal/akhir.']);
        }

        return $trimmed;
    }

    private function authorize(User $actor, string $ability, string|Role $target): void
    {
        Gate::forUser($actor)->authorize($ability, $target);
    }

    private function correlationId(): string
    {
        $value = request()->attributes->get('correlation_id');

        return is_string($value) && Str::isUuid($value) ? $value : (string) Str::uuid();
    }
}
