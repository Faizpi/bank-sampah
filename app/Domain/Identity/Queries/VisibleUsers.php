<?php

declare(strict_types=1);

namespace App\Domain\Identity\Queries;

use App\Authorization\PermissionChecker;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

final readonly class VisibleUsers
{
    public function __construct(private PermissionChecker $permissions) {}

    /** @return Builder<User> */
    public function queryFor(User $actor, UserStatus ...$statuses): Builder
    {
        $statuses = $statuses === [] ? [UserStatus::Active] : $statuses;
        $query = User::query()->whereIn('status', array_map(static fn (UserStatus $status): string => $status->value, $statuses));

        if ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query;
        }

        $query->where(function (Builder $query) use ($actor): void {
            $query->whereKey($actor->getKey());

            if ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.area')) {
                $today = today()->toDateString();

                $query->orWhereHas('customerProfile', function (Builder $customerProfiles) use ($actor, $today): void {
                    $customerProfiles->whereHas('rt', function (Builder $rts) use ($actor, $today): void {
                        $rts->where('is_active', true)
                            ->whereHas('serviceAreas', function (Builder $serviceAreas) use ($actor, $today): void {
                                $serviceAreas->where('is_active', true)
                                    ->whereHas('staffProfiles', function (Builder $staffProfiles) use ($actor, $today): void {
                                        $staffProfiles->where('user_id', $actor->getKey())
                                            ->where(static function (Builder $dates) use ($today): void {
                                                $dates->whereNull('active_from')->orWhere('active_from', '<=', $today);
                                            })
                                            ->where(static function (Builder $dates) use ($today): void {
                                                $dates->whereNull('active_to')->orWhere('active_to', '>=', $today);
                                            });
                                    });
                            });
                    });
                });
            }
        });

        return $query;
    }

    public function canView(User $actor, User $subject): bool
    {
        return $this->queryFor($actor)->whereKey($subject->getKey())->exists();
    }
}
