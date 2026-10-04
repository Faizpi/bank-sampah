<?php

declare(strict_types=1);

namespace App\Domain\Programs\Services;

use App\Domain\Shared\Weight;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

final readonly class EstimateService
{
    public function __construct(private ResolveWastePrice $priceResolver) {}

    /** @return array{waste_type_id: int, condition_id: int, weight_kg: string, price_per_kg: int, estimated_value: int, disclaimer: string} */
    public function calculate(int $wasteTypeId, int $conditionId, string $weightKg): array
    {
        $type = WasteType::query()->with('category')->whereKey($wasteTypeId)->first();
        $condition = WasteCondition::query()->whereKey($conditionId)->first();
        if ($type === null || $condition === null || ! $type->is_active || $type->category?->is_active !== true || ! $condition->is_active) {
            throw ValidationException::withMessages(['waste_type_id' => 'Jenis atau kondisi sampah tidak aktif.']);
        }
        try {
            $weight = Weight::fromDecimal($weightKg);
        } catch (\Throwable) {
            throw ValidationException::withMessages(['weight_kg' => 'Berat harus positif dengan maksimal tiga desimal.']);
        }
        $price = $this->priceResolver->resolve($type, $condition->id, CarbonImmutable::now('Asia/Jakarta'));
        $value = $weight->subtotal($price->money())->amount();

        return ['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => $weight->decimal(), 'price_per_kg' => $price->price, 'estimated_value' => $value, 'disclaimer' => 'Estimasi informatif. Nilai akhir mengikuti berat aktual dan harga saat transaksi. Estimasi tidak membuat transaksi, saldo, hold, atau jaminan nilai akhir.'];
    }
}
