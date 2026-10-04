<?php

declare(strict_types=1);

namespace App\Domain\Identity\Actions;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

final readonly class ManageRoles
{
    /**
     * @param  list<int>  $permissionIds
     */
    public function createRole(User $actor, string $name, string $description, array $permissionIds): Role
    {
        $this->authorize($actor, 'create', Role::class);

        $role = Role::query()->create([
            'name' => $this->normalizedName($name),
            'description' => $description,
        ]);

        $this->syncPermissions($actor, $role, $permissionIds);

        return $role;
    }

    /**
     * @param  list<int>  $permissionIds
     */
    public function updateRole(User $actor, Role $role, string $description, array $permissionIds): void
    {
        $this->authorize($actor, 'update', $role);

        $role->forceFill(['description' => $description])->save();
        $this->syncPermissions($actor, $role, $permissionIds);
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
}
