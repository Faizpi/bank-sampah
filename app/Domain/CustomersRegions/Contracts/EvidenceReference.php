<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use App\Domain\Shared\InvalidValue;

final readonly class EvidenceReference
{
    private function __construct(public int $mediaId) {}

    public static function privateMedia(int $mediaId): self
    {
        if ($mediaId < 1) {
            throw new InvalidValue('Bukti wajib merujuk media privat yang valid.');
        }

        return new self($mediaId);
    }
}
