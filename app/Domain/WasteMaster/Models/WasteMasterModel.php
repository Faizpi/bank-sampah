<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use App\Domain\WasteMaster\Support\WasteMasterBuilder;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use Illuminate\Database\Eloquent\Model;
use LogicException;

abstract class WasteMasterModel extends Model
{
    public $timestamps = false;

    /** @return WasteMasterBuilder<$this> */
    public function newEloquentBuilder($query): WasteMasterBuilder
    {
        return WasteMasterBuilder::forWasteMaster($query, $this);
    }

    public function save(array $options = []): bool
    {
        WasteMasterMutationGuard::ensureAllowed();

        return parent::save($options);
    }

    public function delete(): ?bool
    {
        throw new LogicException('Waste master records are preserved as inactive history and cannot be deleted.');
    }
}
