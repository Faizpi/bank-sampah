<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

final readonly class AssistedServiceHandoff
{
    /**
     * @param  array{number: string, date: string, weight_kg: string, value: int, status: string}  $receipt
     */
    public function __construct(
        public int $ownerId,
        public int $operatorId,
        public int $depositId,
        public array $receipt,
        public int $availableBalance,
    ) {}
}
