<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use JsonSerializable;

final readonly class OpaqueToken implements JsonSerializable
{
    private const REDACTED = '[REDACTED]';

    private function __construct(
        private string $ciphertext,
        private string $mask,
    ) {}

    public static function fromEncoded(string $encoded): self
    {
        if ($encoded === '' || trim($encoded) !== $encoded || preg_match('/[\x00-\x1F\x7F]/', $encoded) === 1) {
            throw new InvalidValue('Token tidak boleh kosong, dinormalisasi, atau memuat karakter kontrol.');
        }

        $mask = random_bytes(strlen($encoded));

        return new self($encoded ^ $mask, $mask);
    }

    public static function generate(): self
    {
        return self::fromEncoded(rtrim(strtr(base64_encode(random_bytes(32)), '+/', '-_'), '='));
    }

    public function equals(self $other): bool
    {
        return hash_equals($this->revealForPersistence(), $other->revealForPersistence());
    }

    public function revealForPersistence(): string
    {
        return $this->ciphertext ^ $this->mask;
    }

    public function __toString(): string
    {
        return self::REDACTED;
    }

    /** @return array{value: string} */
    public function __debugInfo(): array
    {
        return ['value' => self::REDACTED];
    }

    public function jsonSerialize(): string
    {
        return self::REDACTED;
    }
}
