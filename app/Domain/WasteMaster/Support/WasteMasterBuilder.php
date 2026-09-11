<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Support;

use App\Domain\WasteMaster\Models\WasteMasterModel;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of WasteMasterModel
 *
 * @extends Builder<TModel>
 */
final class WasteMasterBuilder extends Builder
{
    /**
     * @template TWasteMaster of WasteMasterModel
     *
     * @param  \Illuminate\Database\Query\Builder  $query
     * @param  TWasteMaster  $model
     * @return WasteMasterBuilder<TWasteMaster>
     */
    public static function forWasteMaster($query, WasteMasterModel $model): self
    {
        return (new self($query))->setModel($model);
    }

    /** @param array<string, mixed> $values */
    public function update(array $values): int
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::update($values);
    }

    public function delete(): int
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::delete();
    }
}
