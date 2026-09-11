<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use OverflowException;

final readonly class Weight
{
    private function __construct(private int $grams) {}

    public static function fromDecimal(string $value): self
    {
        if (preg_match('/^(?:0\.[0-9]{1,3}|[1-9][0-9]*(?:\.[0-9]{1,3})?)$/D', $value) !== 1) {
            throw new InvalidValue('Berat harus berupa desimal positif kanonik dengan maksimal tiga angka desimal.');
        }

        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '');
        if (strlen($whole) > strlen((string) intdiv(PHP_INT_MAX, 1000))) {
            throw new InvalidValue('Berat melampaui batas integer.');
        }

        $wholeNumber = (int) $whole;
        if ($wholeNumber > intdiv(PHP_INT_MAX, 1000)) {
            throw new InvalidValue('Berat melampaui batas integer.');
        }

        $fractionGrams = (int) str_pad($fraction, 3, '0');
        $baseGrams = $wholeNumber * 1000;
        if ($fractionGrams > PHP_INT_MAX - $baseGrams) {
            throw new InvalidValue('Berat melampaui batas integer.');
        }

        $grams = $baseGrams + $fractionGrams;
        if ($grams === 0) {
            throw new InvalidValue('Berat harus lebih dari nol.');
        }

        return new self($grams);
    }

    public static function fromGrams(int $grams): self
    {
        if ($grams <= 0) {
            throw new InvalidValue('Berat harus lebih dari nol.');
        }

        return new self($grams);
    }

    public function grams(): int
    {
        return $this->grams;
    }

    public function decimal(): string
    {
        $whole = intdiv($this->grams, 1000);
        $fraction = $this->grams % 1000;

        return $fraction === 0 ? (string) $whole : $whole.'.'.rtrim(str_pad((string) $fraction, 3, '0', STR_PAD_LEFT), '0');
    }

    public function subtotal(Money $pricePerKilogram): Money
    {
        $price = $pricePerKilogram->amount();
        if ($price !== 0 && $this->grams > intdiv(PHP_INT_MAX - 500, $price)) {
            throw new OverflowException('Perhitungan subtotal melampaui batas integer.');
        }

        return Money::rupiah(intdiv(($this->grams * $price) + 500, 1000));
    }

    public function equals(self $other): bool
    {
        return $this->grams === $other->grams;
    }
}
