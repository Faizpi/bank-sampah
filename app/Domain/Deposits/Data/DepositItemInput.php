<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Data;

use App\Domain\Shared\InvalidValue;
use App\Domain\Shared\Weight;

final readonly class DepositItemInput
{
    public function __construct(
        public int $wasteTypeId,
        public int $conditionId,
        public string $weightKg,
    ) {
        if ($wasteTypeId < 1 || $conditionId < 1) {
            throw new InvalidValue('Jenis dan kondisi sampah wajib dipilih.');
        }

        Weight::fromDecimal($weightKg);
    }

    /** @param array<string, mixed> $input */
    public static function fromArray(array $input): self
    {
        $typeId = $input['waste_type_id'] ?? null;
        $conditionId = $input['waste_condition_id'] ?? ($input['condition_id'] ?? null);
        $weight = $input['weight_kg'] ?? ($input['weight'] ?? null);

        if (! is_numeric($typeId) || ! is_numeric($conditionId) || ! is_string($weight)) {
            throw new InvalidValue('Detail setoran tidak lengkap.');
        }

        return new self((int) $typeId, (int) $conditionId, $weight);
    }
}
