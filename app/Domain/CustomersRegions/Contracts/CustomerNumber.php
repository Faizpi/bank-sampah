<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;

final readonly class CustomerNumber
{
    private function __construct(private string $value) {}

    public static function fromString(string $value): self
    {
        if (preg_match('/^CST-[0-9]{8}$/', $value) !== 1) {
            throw new InvalidValue('Nomor nasabah harus mengikuti pola CST-########.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
