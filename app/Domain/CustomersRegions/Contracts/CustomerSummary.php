<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;
use Livewire\Wireable;

final readonly class CustomerSummary implements Wireable
{
    public function __construct(
        public int $userId,
        public string $name,
        public CustomerNumber $number,
    ) {}

    public function maskedNumber(): string
    {
        return substr($this->number->value(), 0, 4).'****'.substr($this->number->value(), -2);
    }

    /** @return array{userId: int, name: string, number: string} */
    public function toLivewire(): array
    {
        return ['userId' => $this->userId, 'name' => $this->name, 'number' => $this->number->value()];
    }

    public static function fromLivewire(mixed $value): self
    {
        if (! is_array($value) || ! isset($value['userId'], $value['name'], $value['number']) || ! is_int($value['userId']) || ! is_string($value['name']) || ! is_string($value['number'])) {
            throw new InvalidValue('Kandidat nasabah Livewire tidak valid.');
        }

        return new self($value['userId'], $value['name'], CustomerNumber::fromString($value['number']));
    }
}
