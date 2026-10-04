<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;

final readonly class Consent
{
    private function __construct(public string $version, public \DateTimeImmutable $givenAt) {}

    public static function given(string $version): self
    {
        if ($version === '' || trim($version) !== $version || preg_match('/[\x00-\x1F\x7F]/', $version) === 1) {
            throw new InvalidValue('Versi persetujuan wajib diisi dengan nilai yang aman.');
        }

        return new self($version, new \DateTimeImmutable);
    }
}
