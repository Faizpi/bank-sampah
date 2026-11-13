<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Str;

trait HasUuidRouteReference
{
    public function getRouteKey(): mixed
    {
        return $this->getAttribute('uuid') ?? $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): mixed
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }
        $column = Str::isUuid((string) $value) ? 'uuid' : $this->getKeyName();

        return $this->where($column, $value)->first();
    }
}
