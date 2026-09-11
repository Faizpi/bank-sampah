<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;
use JsonSerializable;

final readonly class QrToken implements JsonSerializable
{
    private function __construct(private string $value) {}

    public static function generate(): self
    {
        return new self(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public static function fromValue(string $value): self
    {
        if (preg_match('/^[A-Za-z0-9_-]{43}$/', $value) !== 1) {
            throw new InvalidValue('Token QR tidak valid.');
        }

        return new self($value);
    }

    public function value(): string
    {
        return $this->value;
    }

    public function hash(): string
    {
        return hash('sha256', $this->value);
    }

    public function matches(self $other): bool
    {
        return hash_equals($this->hash(), $other->hash());
    }

    public function __toString(): string
    {
        return '[REDACTED]';
    }

    public function jsonSerialize(): string
    {
        return '[REDACTED]';
    }
}
