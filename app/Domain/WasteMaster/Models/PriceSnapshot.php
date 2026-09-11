<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use App\Domain\Shared\Money;
use App\Domain\Shared\Weight;
use InvalidArgumentException;

final readonly class PriceSnapshot
{
    private function __construct(
        public string $wasteTypeCode,
        public string $wasteTypeName,
        public string $unitCode,
        public string $unitName,
        public string $unitSymbol,
        public string $conditionCode,
        public string $conditionName,
        public int $pricePerUnit,
        public string $weightKg,
        public int $subtotal,
        public string $roundingVersion,
        public string $effectiveFrom,
    ) {}

    public static function fromPrice(WastePrice $price): self
    {
        $type = $price->wasteType;
        $condition = $price->condition;

        if ($type === null || $condition === null) {
            throw new InvalidArgumentException('Price snapshot requires a complete waste price context.');
        }

        $unit = $type->unit;

        if ($unit === null) {
            throw new InvalidArgumentException('Price snapshot requires a complete waste price context.');
        }

        return new self(
            wasteTypeCode: $type->code,
            wasteTypeName: $type->name,
            unitCode: $unit->code,
            unitName: $unit->name,
            unitSymbol: $unit->symbol,
            conditionCode: $condition->code,
            conditionName: $condition->name,
            pricePerUnit: $price->price,
            weightKg: '0',
            subtotal: 0,
            roundingVersion: $price->rounding_version,
            effectiveFrom: $price->effectiveFromDate()->toIso8601String(),
        );
    }

    public function withWeight(string $weightKg): self
    {
        $weight = Weight::fromDecimal($weightKg);
        $subtotal = $weight->subtotal(Money::rupiah($this->pricePerUnit))->amount();

        return new self(
            wasteTypeCode: $this->wasteTypeCode,
            wasteTypeName: $this->wasteTypeName,
            unitCode: $this->unitCode,
            unitName: $this->unitName,
            unitSymbol: $this->unitSymbol,
            conditionCode: $this->conditionCode,
            conditionName: $this->conditionName,
            pricePerUnit: $this->pricePerUnit,
            weightKg: $weight->decimal(),
            subtotal: $subtotal,
            roundingVersion: $this->roundingVersion,
            effectiveFrom: $this->effectiveFrom,
        );
    }

    /** @return array<string, int|string> */
    public function toArray(): array
    {
        return [
            'waste_type_code' => $this->wasteTypeCode,
            'waste_type_name' => $this->wasteTypeName,
            'unit_code' => $this->unitCode,
            'unit_name' => $this->unitName,
            'unit_symbol' => $this->unitSymbol,
            'condition_code' => $this->conditionCode,
            'condition_name' => $this->conditionName,
            'price_per_unit' => $this->pricePerUnit,
            'weight_kg' => $this->weightKg,
            'subtotal' => $this->subtotal,
            'rounding_version' => $this->roundingVersion,
            'effective_from' => $this->effectiveFrom,
        ];
    }
}
