<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class PickupPhotoRetentionResult
{
    public function __construct(
        public int $deletedCount,
        public int $deletedBytes,
        public int $missingFileCount,
    ) {}
}
