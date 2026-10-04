<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use App\Domain\Shared\Money;
use App\Models\User;
use Carbon\CarbonInterface;
use Database\Factories\WastePriceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use LogicException;

final class WastePrice extends WasteMasterModel
{
    /** @use HasFactory<WastePriceFactory> */
    use HasFactory;

    protected $fillable = [
        'waste_type_id',
        'waste_condition_id',
        'price',
        'effective_from',
        'effective_to',
        'created_by',
        'rounding_version',
    ];

    protected static function newFactory(): WastePriceFactory
    {
        return WastePriceFactory::new();
    }

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'effective_from' => 'immutable_datetime',
            'effective_to' => 'immutable_datetime',
            'created_by' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        self::deleting(static function (): void {
            throw new LogicException('Waste price history is immutable and cannot be deleted.');
        });
    }

    /** @return BelongsTo<WasteType, $this> */
    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    /** @return BelongsTo<WasteCondition, $this> */
    public function condition(): BelongsTo
    {
        return $this->belongsTo(WasteCondition::class, 'waste_condition_id');
    }

    /** @return BelongsTo<User, $this> */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function effectiveFromDate(): CarbonInterface
    {
        $value = $this->getAttribute('effective_from');

        return $value instanceof CarbonInterface ? $value : Carbon::parse((string) $value);
    }

    public function effectiveToDate(): ?CarbonInterface
    {
        $value = $this->getAttribute('effective_to');

        return $value === null ? null : ($value instanceof CarbonInterface ? $value : Carbon::parse((string) $value));
    }

    public function appliesAt(CarbonInterface $at): bool
    {
        $from = $this->effectiveFromDate();
        $to = $this->effectiveToDate();

        return $from <= $at && ($to === null || $at < $to);
    }

    public function money(): Money
    {
        return Money::rupiah($this->price);
    }

    public function snapshot(): PriceSnapshot
    {
        $this->loadMissing(['wasteType.unit', 'condition']);

        return PriceSnapshot::fromPrice($this);
    }

    /** @return array<string, mixed> */
    public function toPublicArray(): array
    {
        $this->loadMissing(['wasteType.category', 'wasteType.unit', 'condition']);

        return [
            'code' => $this->wasteType->code,
            'name' => $this->wasteType->name,
            'category' => $this->wasteType->category->name,
            'condition' => $this->condition->name,
            'unit' => $this->wasteType->unit->symbol,
            'price' => $this->price,
            'effective_from' => $this->effectiveFromDate()->toIso8601String(),
            'effective_to' => $this->effectiveToDate()?->toIso8601String(),
        ];
    }
}
