<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use App\Domain\CustomersRegions\Support\RegionBuilder;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use Illuminate\Database\Eloquent\Model;
use LogicException;

/** @property bool $is_active */
abstract class RegionModel extends Model
{
    /** @return RegionBuilder<$this> */
    public function newEloquentBuilder($query): RegionBuilder
    {
        return RegionBuilder::forRegion($query, $this);
    }

    public function save(array $options = []): bool
    {
        if ($this->exists) {
            RegionMutationGuard::ensureAllowed();
        }

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Regional records are preserved as inactive history and cannot be deleted.');
    }
}
