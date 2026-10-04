<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use OverflowException;

final readonly class Money
{
    private function __construct(private int $amount) {}

    public static function rupiah(int $amount): self
    {
        if ($amount < 0) {
            throw new InvalidValue('Nilai rupiah tidak boleh negatif.');
        }

        return new self($amount);
    }

    public function amount(): int
    {
        return $this->amount;
    }

    public function add(self $other): self
    {
        if ($other->amount > PHP_INT_MAX - $this->amount) {
            throw new OverflowException('Penjumlahan rupiah melampaui batas integer.');
        }

        return new self($this->amount + $other->amount);
    }

    public function subtract(self $other): self
    {
        if ($other->amount > $this->amount) {
            throw new InvalidValue('Pengurangan rupiah tidak boleh menghasilkan nilai negatif.');
        }

        return new self($this->amount - $other->amount);
    }

    public function equals(self $other): bool
    {
        return $this->amount === $other->amount;
    }
}
