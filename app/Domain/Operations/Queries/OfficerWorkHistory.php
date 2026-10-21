<?php

declare(strict_types=1);

namespace App\Domain\Operations\Queries;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** Past work belongs to its operator, not every operator in the same service area. */
final readonly class OfficerWorkHistory
{
    public function __construct(private PermissionChecker $permissions) {}

    /** @return Builder<Deposit> */
    public function deposits(User $actor): Builder
    {
        $this->authorize($actor);

        return Deposit::query()->where('staff_id', $actor->id)
            ->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED, Deposit::STATUS_REVERSED])
            ->with(['customer', 'items', 'correction']);
    }

    /** @return Builder<PickupRequest> */
    public function pickups(User $actor): Builder
    {
        $this->authorize($actor);

        return PickupRequest::query()->where('assigned_staff_id', $actor->id)
            ->whereIn('status', [PickupStatus::Completed, PickupStatus::Cancelled, PickupStatus::Rejected])
            ->with(['customer', 'deposit.correction']);
    }

    /** @return Builder<GroceryRedemption> */
    public function groceries(User $actor): Builder
    {
        $this->authorize($actor);

        return GroceryRedemption::query()->where('status', GroceryStatus::Completed)
            ->where(fn (Builder $query): Builder => $query->where('claimed_by_id', $actor->id)->orWhere('handover_actor_id', $actor->id))
            ->with(['customer', 'handoverActor']);
    }

    private function authorize(User $actor): void
    {
        abort_unless($actor->hasRole('petugas') && $this->permissions->allows($actor, 'user.view'), 403);
    }
}
