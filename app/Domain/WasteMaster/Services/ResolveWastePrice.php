<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Services;

use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use Carbon\CarbonInterface;
use Illuminate\Validation\ValidationException;

final readonly class ResolveWastePrice
{
    public function resolve(WasteType $type, int $conditionId, CarbonInterface $at): WastePrice
    {
        $price = WastePrice::query()
            ->with(['wasteType.unit', 'condition'])
            ->where('waste_type_id', $type->id)
            ->where('waste_condition_id', $conditionId)
            ->where('effective_from', '<=', $at)
            ->where(static function ($query) use ($at): void {
                $query->whereNull('effective_to')->orWhere('effective_to', '>', $at);
            })
            ->orderByDesc('effective_from')
            ->first();

        if ($price === null || ! $price->wasteType->is_active || ! $price->wasteType->category->is_active || ! $price->condition->is_active) {
            throw ValidationException::withMessages(['price' => 'Tidak ada harga aktif untuk jenis dan kondisi pada waktu tersebut.']);
        }

        return $price;
    }
}
