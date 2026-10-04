<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Queries;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\CustomerNumber;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\Identity\Enums\UserStatus;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class SearchCustomers
{
    public function __construct(private PermissionChecker $permissions) {}

    /** @return list<CustomerSummary> */
    public function search(User $actor, string $term, int $limit = 10): array
    {
        $this->ensureAllowed($actor);
        $normalized = $this->normalize($term);

        if ($normalized === '') {
            return [];
        }

        $customers = $this->scopedQuery($actor)
            ->where(function (Builder $query) use ($normalized): void {
                $query->whereHas('customerProfile', static fn (Builder $profile): Builder => $profile->where('customer_number', 'like', $normalized.'%'))
                    ->orWhere('name', 'like', $normalized.'%');
            })
            ->with('customerProfile')
            ->limit(max(1, min($limit, 25)))
            ->get();

        return $customers->map(static function (User $user): CustomerSummary {
            $number = CustomerNumber::fromString((string) $user->customerProfile?->customer_number);

            return new CustomerSummary($user->getKey(), $user->name, $number);
        })->all();
    }

    private function ensureAllowed(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'customer.view')) {
            throw new AuthorizationException('Anda tidak memiliki akses untuk melihat nasabah.');
        }
    }

    /** @return Builder<User> */
    private function scopedQuery(User $actor): Builder
    {
        $query = User::query()->where('status', UserStatus::Active->value);

        if ($this->permissions->allows($actor, 'user.view') && $this->permissions->allows($actor, 'user.view.all')) {
            return $query->whereHas('customerProfile');
        }

        if (! $this->permissions->allows($actor, 'user.view') || ! $this->permissions->allows($actor, 'user.view.area')) {
            return $query->whereKey($actor->getKey())->whereHas('customerProfile');
        }

        $today = today()->toDateString();

        return $query->where(function (Builder $scope) use ($actor, $today): void {
            $scope->whereHas('customerProfile.rt', static function (Builder $rt) use ($actor, $today): void {
                $rt->where('is_active', true)->whereHas('serviceAreas', static function (Builder $area) use ($actor, $today): void {
                    $area->where('is_active', true)->whereHas('staffProfiles', static function (Builder $staff) use ($actor, $today): void {
                        $staff->where('user_id', $actor->getKey())
                            ->where(static fn (Builder $dates): Builder => $dates->whereNull('active_from')->orWhere('active_from', '<=', $today))
                            ->where(static fn (Builder $dates): Builder => $dates->whereNull('active_to')->orWhere('active_to', '>=', $today));
                    });
                });
            })->orWhere(function (Builder $own) use ($actor): void {
                $own->whereKey($actor->getKey())->whereHas('customerProfile');
            });
        });
    }

    private function normalize(string $term): string
    {
        $value = preg_replace('/\s+/u', ' ', trim($term)) ?? trim($term);

        if ($value === '' || preg_match('/[\x00-\x1F\x7F]/', $value) === 1 || Str::length($value) > 120) {
            throw ValidationException::withMessages(['search' => 'Pencarian nasabah tidak valid.']);
        }

        return $value;
    }
}
