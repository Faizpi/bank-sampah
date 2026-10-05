<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Actions;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class ManageWastePricing
{
    public function __construct(private AuditLogger $auditLogger) {}

    public function createPeriod(
        User $actor,
        WasteType $type,
        WasteCondition $condition,
        int $price,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
        string $correlationId,
        bool $zeroPriceConfirmed = false,
    ): WastePrice {
        if ($price < 0 || $price > 9_000_000_000_000_000) {
            throw ValidationException::withMessages(['price' => 'Harga harus berupa integer rupiah 0 sampai 9.000.000.000.000.000.']);
        }

        if ($price === 0 && ! $zeroPriceConfirmed) {
            throw ValidationException::withMessages(['price' => 'Harga nol memerlukan konfirmasi kebijakan penerimaan tanpa nilai.']);
        }

        if ($effectiveTo !== null && $effectiveTo <= $effectiveFrom) {
            throw ValidationException::withMessages(['effective_to' => 'Berlaku sampai harus sesudah berlaku mulai.']);
        }

        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $type, $condition, $price, $effectiveFrom, $effectiveTo, $correlationId): WastePrice {
            $type = $type->fresh(['category']) ?? throw ValidationException::withMessages(['waste_type_id' => 'Jenis sampah tidak ditemukan.']);
            $condition = $condition->fresh() ?? throw ValidationException::withMessages(['waste_condition_id' => 'Kondisi sampah tidak ditemukan.']);

            if (! $type->is_active || ! $type->category->is_active) {
                throw ValidationException::withMessages(['waste_type_id' => 'Jenis sampah harus aktif.']);
            }

            if (! $condition->is_active || ! $type->conditions()->whereKey($condition->id)->exists()) {
                throw ValidationException::withMessages(['waste_condition_id' => 'Kondisi harus aktif dan diterima oleh jenis sampah.']);
            }

            $existing = WastePrice::query()
                ->where('waste_type_id', $type->id)
                ->where('waste_condition_id', $condition->id)
                ->lockForUpdate()
                ->get();

            $overlapping = $existing->filter(fn (WastePrice $period): bool => $this->overlaps($period, $effectiveFrom, $effectiveTo));
            $closable = $overlapping->filter(fn (WastePrice $period): bool => $period->effective_to === null && $period->effective_from < $effectiveFrom);

            if ($overlapping->isNotEmpty() && $closable->count() !== 1) {
                throw ValidationException::withMessages(['effective_from' => 'Periode harga tumpang tindih dengan riwayat yang sudah ada.']);
            }

            foreach ($closable as $period) {
                $oldValues = ['effective_to' => null];
                WasteMasterMutationGuard::run(fn (): int => WastePrice::query()->whereKey($period->id)->update(['effective_to' => $effectiveFrom]));
                $this->auditLogger->record(
                    $actor,
                    'waste.price.closed',
                    $period,
                    $oldValues,
                    ['effective_to' => $effectiveFrom->toIso8601String()],
                    $this->normalizedCorrelationId($correlationId),
                );
            }

            $newPrice = WasteMasterMutationGuard::run(fn (): WastePrice => WastePrice::query()->create([
                'waste_type_id' => $type->id,
                'waste_condition_id' => $condition->id,
                'price' => $price,
                'effective_from' => $effectiveFrom,
                'effective_to' => $effectiveTo,
                'created_by' => $actor->id,
                'rounding_version' => 'half_up_v1',
            ]));

            $this->auditLogger->record(
                $actor,
                'waste.price.created',
                $newPrice,
                [],
                [
                    'waste_type_id' => $type->id,
                    'waste_condition_id' => $condition->id,
                    'price' => $price,
                    'effective_from' => $effectiveFrom->toIso8601String(),
                    'effective_to' => $effectiveTo?->toIso8601String(),
                ],
                $this->normalizedCorrelationId($correlationId),
            );

            return $newPrice->load(['wasteType.unit', 'condition']);
        });
    }

    public function replacePeriod(
        User $actor,
        WastePrice $current,
        int $price,
        CarbonImmutable $effectiveFrom,
        ?CarbonImmutable $effectiveTo,
        string $correlationId,
        bool $zeroPriceConfirmed = false,
    ): WastePrice {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $current, $price, $effectiveFrom, $effectiveTo, $correlationId, $zeroPriceConfirmed): WastePrice {
            /** @var WastePrice|null $locked */
            $locked = WastePrice::query()->whereKey($current->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages(['price' => 'Periode harga tidak ditemukan.']);
            }

            if ($locked->effective_to !== null || $effectiveFrom <= $locked->effective_from) {
                throw ValidationException::withMessages(['effective_from' => 'Harga hanya dapat diubah melalui periode baru setelah periode aktif dimulai.']);
            }

            return $this->createPeriod(
                $actor,
                $locked->wasteType,
                $locked->condition,
                $price,
                $effectiveFrom,
                $effectiveTo,
                $correlationId,
                $zeroPriceConfirmed,
            )->fresh(['wasteType.unit', 'condition']) ?? throw ValidationException::withMessages(['price' => 'Harga baru gagal dimuat.']);
        });
    }

    public function deactivatePeriod(
        User $actor,
        WastePrice $current,
        CarbonImmutable $effectiveTo,
        string $correlationId,
    ): WastePrice {
        $this->authorize($actor);

        return DB::transaction(function () use ($actor, $current, $effectiveTo, $correlationId): WastePrice {
            /** @var WastePrice|null $locked */
            $locked = WastePrice::query()->whereKey($current->id)->lockForUpdate()->first();

            if ($locked === null) {
                throw ValidationException::withMessages(['price' => 'Periode harga tidak ditemukan.']);
            }

            if ($locked->effective_to !== null) {
                return $locked->fresh(['wasteType.unit', 'condition']) ?? $locked;
            }

            if ($effectiveTo <= $locked->effective_from) {
                throw ValidationException::withMessages(['effective_to' => 'Waktu nonaktif harus sesudah periode harga dimulai.']);
            }

            WasteMasterMutationGuard::run(fn (): int => WastePrice::query()->whereKey($locked->id)->update(['effective_to' => $effectiveTo]));
            $this->auditLogger->record(
                $actor,
                'waste.price.deactivated',
                $locked,
                ['effective_to' => null],
                ['effective_to' => $effectiveTo->toIso8601String()],
                $this->normalizedCorrelationId($correlationId),
            );

            return $locked->fresh(['wasteType.unit', 'condition']) ?? $locked;
        });
    }

    private function overlaps(WastePrice $period, CarbonImmutable $from, ?CarbonImmutable $to): bool
    {
        return ($to === null || $period->effective_from < $to)
            && ($period->effective_to === null || $from < $period->effective_to);
    }

    private function authorize(User $actor): void
    {
        Gate::forUser($actor)->authorize('create', WastePrice::class);
    }

    private function normalizedCorrelationId(string $correlationId): string
    {
        return Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
