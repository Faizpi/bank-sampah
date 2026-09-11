<?php

declare(strict_types=1);

namespace App\Domain\Identity\Queries;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\MobileServices\Models\MobileService;
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
                            ->whereHas('rw', static fn (Builder $rw): Builder => $rw
                                ->where('is_active', true)
                                ->whereHas('dusun', static fn (Builder $dusun): Builder => $dusun->where('is_active', true)))
                            ->whereHas('serviceAreas', static fn (Builder $serviceAreas): Builder => $serviceAreas
                                ->where('is_active', true)
                                ->whereHas('staffAssignments', function (Builder $assignments) use ($actor, $today): void {
                                    $assignments->where('staff_profile_user_id', $actor->getKey())
                                        ->where(static function (Builder $dates) use ($today): void {
                                            $dates->whereNull('active_from')->orWhere('active_from', '<=', $today);
                                        })
                                        ->where(static function (Builder $dates) use ($today): void {
                                            $dates->whereNull('active_to')->orWhere('active_to', '>=', $today);
                                        });
                                }));
                    });
                });
            }
        });

        return $query;
    }

    /** @return Builder<User> */
    public function queryForMobileService(User $actor, MobileService $service, UserStatus ...$statuses): Builder
    {
        $visibleUserIds = $this->queryFor($actor, ...$statuses)->select('users.id');
        if (! $this->permissions->allows($actor, 'mobile-service.operate') || ! $service->isOpen() || ! $service->staff()->whereKey($actor->getKey())->exists()) {
            return User::query()->whereIn('users.id', $visibleUserIds);
        }

        return User::query()->where(function (Builder $users) use ($visibleUserIds, $service): void {
            $users->whereIn('users.id', $visibleUserIds)
                ->orWhere(function (Builder $mobileCustomers) use ($service): void {
                    $mobileCustomers->where('status', UserStatus::Active->value)
                        ->whereHas('customerProfile.rt', static function (Builder $rt) use ($service): void {
                            $rt->where('is_active', true)
                                ->whereHas('rw', static fn (Builder $rw): Builder => $rw
                                    ->where('is_active', true)
                                    ->whereHas('dusun', static fn (Builder $dusun): Builder => $dusun->where('is_active', true)))
                                ->when($service->rt_id !== null, static fn (Builder $scope): Builder => $scope->whereKey($service->rt_id))
                                ->when($service->rt_id === null && $service->rw_id !== null, static fn (Builder $scope): Builder => $scope->where('rw_id', $service->rw_id));
                        });
                });
        });
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
                ->whereHas('staffAssignments', static fn (Builder $assignments): Builder => $assignments
                    ->where('staff_profile_user_id', $actor->getKey())
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
