<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Support;

use App\Domain\CustomersRegions\Models\RegionModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of \App\Domain\CustomersRegions\Models\RegionModel
 *
 * @extends Builder<TModel>
 */
final class RegionBuilder extends Builder
{
    /**
     * @template TRegion of \App\Domain\CustomersRegions\Models\RegionModel
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  TRegion  $model
     * @return RegionBuilder<TRegion>
     */
    public static function forRegion($query, RegionModel $model): self
    {
        return (new self($query))->setModel($model);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        RegionMutationGuard::ensureAllowed();

        return parent::update($values);
    }

    public function delete(): int
    {
        RegionMutationGuard::ensureAllowed();

        return parent::delete();
    }
}
