<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

use DateTimeImmutable;

final readonly class AssistedServiceRecord
{
    public function __construct(
        public int $id,
        public int $ownerId,
        public int $operatorId,
        public string $serviceType,
        public string $consentVersion,
        public DateTimeImmutable $consentedAt,
        public int $evidenceMediaId,
        public ?int $depositId = null,
        public ?int $withdrawalId = null,
    ) {}
}
