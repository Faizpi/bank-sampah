<?php

declare(strict_types=1);

namespace App\Support;

trait HasTransactionRouteReference
{
    protected function transactionReferenceColumn(): string
    {
        return 'request_number';
    }

    public function getRouteKey(): mixed
    {
        return $this->getAttribute($this->transactionReferenceColumn()) ?? $this->getKey();
    }

    public function resolveRouteBinding($value, $field = null): mixed
    {
        if ($field !== null) {
            return parent::resolveRouteBinding($value, $field);
        }
        // Legacy numeric links remain usable, with the same authorization checks.
        $column = ctype_digit((string) $value) ? $this->getKeyName() : $this->transactionReferenceColumn();

        return $this->where($column, $value)->first();
    }
}
