<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final readonly class IdempotencyResult
{
    public function __construct(
        public bool $replayed,
        public string $status,
        public ?string $resultType,
        public ?int $resultId,
    ) {}
}
