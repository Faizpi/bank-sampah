<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Support;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

/**
 * @template TRelatedModel of \Illuminate\Database\Eloquent\Model
 * @template TDeclaringModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends BelongsToMany<TRelatedModel, TDeclaringModel, Pivot, 'pivot'>
 */
final class GuardedWasteTypeConditionsRelation extends BelongsToMany
{
    /** @param array<string, mixed> $attributes */
    public function attach($id, array $attributes = [], $touch = true): void
    {
        WasteMasterMutationGuard::ensureAllowed();

        parent::attach($id, $attributes, $touch);
    }

    public function detach($ids = null, $touch = true): int
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::detach($ids, $touch);
    }

    /** @param array<string, mixed> $attributes */
    public function updateExistingPivot($id, array $attributes, $touch = true): int
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::updateExistingPivot($id, $attributes, $touch);
    }

    /**
     * @param  Collection<array-key, Model>|Model|array<array-key, Model|int|string>|int|string  $ids
     * @return array{attached: array<array-key, int|string>, detached: array<array-key, int|string>, updated: array<array-key, int|string>}
     */
    public function sync($ids, $detaching = true): array
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::sync($ids, $detaching);
    }
}
