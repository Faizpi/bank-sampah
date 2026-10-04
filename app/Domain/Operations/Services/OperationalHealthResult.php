<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class OperationalHealthResult
{
    /** @param array<string, OperationalHealthCheck> $checks */
    public function __construct(private array $checks) {}

    public function isHealthy(): bool
    {
        foreach ($this->checks as $check) {
            if (! $check->isHealthy()) {
                return false;
            }
        }

        return true;
    }

    /** @return array<string, array<string, string>> */
    public function toArray(): array
    {
        return array_map(
            static fn (OperationalHealthCheck $check): array => $check->toArray(),
            $this->checks,
        );
    }
}
