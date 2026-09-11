<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class OperationalHealthCheck
{
    /** @param array<string, string> $details */
    private function __construct(
        private OperationalHealthStatus $status,
        private ?string $reasonCode,
        private array $details,
    ) {}

    /** @param array<string, string> $details */
    public static function ok(array $details = []): self
    {
        return new self(OperationalHealthStatus::Ok, null, $details);
    }

    /** @param array<string, string> $details */
    public static function configured(OperationalHealthStatus $heartbeat, string $reasonCode, array $details = []): self
    {
        return new self(OperationalHealthStatus::Configured, $reasonCode, ['heartbeat' => $heartbeat->value, ...$details]);
    }

    public static function degraded(string $reasonCode): self
    {
        return new self(OperationalHealthStatus::Degraded, $reasonCode, []);
    }

    public static function notApplicable(string $reasonCode): self
    {
        return new self(OperationalHealthStatus::NotApplicable, $reasonCode, []);
    }

    public function isHealthy(): bool
    {
        return $this->status !== OperationalHealthStatus::Degraded;
    }

    /** @return array<string, string> */
    public function toArray(): array
    {
        return array_filter([
            'status' => $this->status->value,
            'reason' => $this->reasonCode,
            ...$this->details,
        ], static fn (?string $value): bool => $value !== null);
    }
}
