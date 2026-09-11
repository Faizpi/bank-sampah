<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Support;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class GroceryRedemptionScope
{
    public function __construct(private PermissionChecker $permissions) {}

    /**
     * @param  Builder<GroceryRedemption>  $query
     * @return Builder<GroceryRedemption>
     */
    public function applyTo(Builder $query, User $actor): Builder
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }

        $today = today()->toDateString();

        return $query->whereIn('service_area_id', StaffServiceArea::query()
            ->select('service_area_id')
            ->where('staff_profile_user_id', $actor->id)
            ->where(static fn ($dates) => $dates->whereNull('active_from')->orWhereDate('active_from', '<=', $today))
            ->where(static fn ($dates) => $dates->whereNull('active_to')->orWhereDate('active_to', '>=', $today))
            ->whereHas('serviceArea', static fn ($area) => $area->where('is_active', true)));
    }

    public function canOperate(User $actor, GroceryRedemption $redemption): bool
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return true;
        }
        if ($redemption->service_area_id === null) {
            return false;
        }

        $today = today()->toDateString();

        return StaffServiceArea::query()
            ->where('staff_profile_user_id', $actor->id)
            ->where('service_area_id', $redemption->service_area_id)
            ->where(static fn ($dates) => $dates->whereNull('active_from')->orWhereDate('active_from', '<=', $today))
            ->where(static fn ($dates) => $dates->whereNull('active_to')->orWhereDate('active_to', '>=', $today))
            ->whereHas('serviceArea', static fn ($area) => $area->where('is_active', true))
            ->exists();
    }
}
