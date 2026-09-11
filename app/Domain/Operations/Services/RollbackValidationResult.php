<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class RollbackValidationResult
{
    /** @param list<string> $issues */
    public function __construct(private array $issues) {}

    public function passes(): bool
    {
        return $this->issues === [];
    }

    /** @return list<string> */
    public function issueCodes(): array
    {
        return $this->issues;
    }
}
