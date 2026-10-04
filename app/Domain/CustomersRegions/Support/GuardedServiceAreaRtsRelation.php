<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Support;

use App\Domain\CustomersRegions\Models\Rt;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel, Pivot, 'pivot'>
 */
final class GuardedServiceAreaRtsRelation extends BelongsToMany
{
    /** @param array<string, mixed> $attributes */
    public function attach($id, array $attributes = [], $touch = true): void
    {
        RegionMutationGuard::ensureAllowed();

        parent::attach($id, $attributes, $touch);
    }

    public function detach($ids = null, $touch = true): int
    {
        RegionMutationGuard::ensureAllowed();

        return parent::detach($ids, $touch);
    }

    /** @param array<string, mixed> $attributes */
    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        RegionMutationGuard::ensureAllowed();

        return parent::updateExistingPivot($id, $attributes, $touch);
    }

    /**
     * @param  Collection<array-key, Rt>|Rt|array<array-key, Rt|int|string>|int|string  $ids
     * @return array{attached: array<array-key, int|string>, detached: array<array-key, int|string>, updated: array<array-key, int|string>}
     */
    public function sync($ids, $detaching = true): array
    {
        RegionMutationGuard::ensureAllowed();

        return parent::sync($ids, $detaching);
    }
}
