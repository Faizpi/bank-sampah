<?php

declare(strict_types=1);

namespace App\Policies;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Models\User;

final readonly class GroceryRedemptionPolicy
{
    public function __construct(
        private PermissionChecker $permissions,
        private GroceryService $groceries,
    ) {}

    public function viewAny(User $actor): bool
    {
        return $this->permissions->allows($actor, 'grocery.view');
    }

    public function view(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->groceries->canView($actor, $redemption);
    }

    public function approve(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->permissions->allows($actor, 'grocery.approve') && $this->groceries->canView($actor, $redemption);
    }

    public function prepare(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->permissions->allows($actor, 'grocery.prepare') && $this->groceries->canView($actor, $redemption);
    }

    public function handover(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->permissions->allows($actor, 'grocery.handover') && $this->groceries->canView($actor, $redemption);
    }

    public function cancel(User $actor, GroceryRedemption $redemption): bool
    {
        return $this->permissions->allows($actor, 'grocery.cancel') && $this->groceries->canView($actor, $redemption);
    }
}
