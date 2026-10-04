<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

final readonly class PrivateStorageBoundaryResult
{
    private function __construct(
        private bool $valid,
        private string $reasonCode,
    ) {}

    public static function valid(): self
    {
        return new self(true, 'ok');
    }

    public static function invalid(string $reasonCode): self
    {
        return new self(false, $reasonCode);
    }

    public function isValid(): bool
    {
        return $this->valid;
    }

    public function reasonCode(): string
    {
        return $this->reasonCode;
    }
}
