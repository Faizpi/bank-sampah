<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use LogicException;

/**
 * @template TValue
 * @template TError
 */
final readonly class Result
{
    private function __construct(
        private bool $successful,
        private mixed $value,
        private mixed $error,
    ) {}

    /**
     * @template TSuccess
     *
     * @param  TSuccess  $value
     * @return self<TSuccess, never>
     */
    public static function success(mixed $value): self
    {
        return new self(true, $value, null);
    }

    /**
     * @template TFailure
     *
     * @param  TFailure  $error
     * @return self<never, TFailure>
     */
    public static function failure(mixed $error): self
    {
        return new self(false, null, $error);
    }

    public function isSuccess(): bool
    {
        return $this->successful;
    }

    public function isFailure(): bool
    {
        return ! $this->successful;
    }

    /** @return TValue */
    public function value(): mixed
    {
        if ($this->isFailure()) {
            throw new LogicException('A failed result does not contain a value.');
        }

        return $this->value;
    }

    /** @return TError */
    public function error(): mixed
    {
        if ($this->isSuccess()) {
            throw new LogicException('A successful result does not contain an error.');
        }

        return $this->error;
    }
}
