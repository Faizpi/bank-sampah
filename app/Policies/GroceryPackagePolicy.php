<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Models\User;

final readonly class GroceryPackagePolicy
{
    public function __construct(private PermissionChecker $permissions) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'grocery.package.view');
    }

    public function view(User $actor, GroceryPackage $package): bool
    {
        return $this->permissions->allows($actor, 'grocery.package.view');
    }

    public function create(User $actor): bool
    {
        return $this->permissions->allows($actor, 'grocery.package.manage');
    }

    public function update(User $actor, GroceryPackage $package): bool
    {
        return $this->permissions->allows($actor, 'grocery.package.manage');
    }

    public function deactivate(User $actor, GroceryPackage $package): bool
    {
        return $this->permissions->allows($actor, 'grocery.package.manage');
    }
}
