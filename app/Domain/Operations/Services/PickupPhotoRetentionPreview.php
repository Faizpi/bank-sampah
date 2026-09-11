<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class PickupPhotoRetentionPreview
{
    /**
     * @param  list<array{id: int, pickup_number: string, pickup_status: string, original_name: string, size: int, created_at: string, file_exists: bool}>  $items
     */
    public function __construct(
        public string $before,
        public int $deletableCount,
        public int $deletableBytes,
        public int $batchCount,
        public int $batchMissingFileCount,
        public int $batchLimit,
        public array $items,
    ) {}
}
