<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Services;

final readonly class AuditRetentionPreview
{
    /** @param list<int> $candidateIds */
    public function __construct(
        public string $before,
        public int $deletableCount,
        public int $protectedCount,
        public array $candidateIds = [],
    ) {}

    /** @return array{before: string, deletable_count: int, protected_count: int} */
    public function toArray(): array
    {
        return [
            'before' => $this->before,
            'deletable_count' => $this->deletableCount,
            'protected_count' => $this->protectedCount,
        ];
    }
}
