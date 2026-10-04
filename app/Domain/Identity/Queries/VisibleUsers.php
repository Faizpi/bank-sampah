<?php

declare(strict_types=1);

namespace App\Domain\Identity\Queries;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\Rt;
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

    public function canView(User $actor, User $subject, UserStatus ...$statuses): bool
    {
        return $this->queryFor($actor, ...$statuses)->whereKey($subject->getKey())->exists();
    }

    public function canAccessCustomerRt(User $actor, int $rtId): bool
    {
        return in_array($rtId, $this->accessibleRtIds($actor), true);
    }

    /** @return list<int> */
    public function accessibleRtIds(User $actor): array
    {
        $query = Rt::query()
            ->where('is_active', true)
            ->whereHas('rw', static fn (Builder $rw): Builder => $rw
                ->where('is_active', true)
                ->whereHas('dusun', static fn (Builder $dusun): Builder => $dusun->where('is_active', true)));

        if ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query->pluck('id')->map(static fn (mixed $id): int => (int) $id)->values()->all();
        }

        if (! $this->permissions->allows($actor, 'user.view') || ! $this->permissions->allows($actor, 'user.view.area')) {
            return [];
        }

        $today = today()->toDateString();

        return $query
            ->whereHas('serviceAreas', static fn (Builder $serviceAreas): Builder => $serviceAreas
                ->where('is_active', true)
                ->whereHas('staffProfiles', static fn (Builder $staffProfiles): Builder => $staffProfiles
                    ->where('user_id', $actor->getKey())
                    ->where(static function (Builder $dates) use ($today): void {
                        $dates->whereNull('active_from')->orWhere('active_from', '<=', $today);
                    })
                    ->where(static function (Builder $dates) use ($today): void {
                        $dates->whereNull('active_to')->orWhere('active_to', '>=', $today);
                    })))
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }
}
