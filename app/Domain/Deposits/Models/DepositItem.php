<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Models;

use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property string $weight_kg
 * @property int|null $subtotal
 * @property WasteType|null $wasteType
 */
final class DepositItem extends Model
{
    protected $fillable = [
        'deposit_id', 'waste_type_id', 'waste_condition_id', 'weight_kg',
        'waste_type_code', 'waste_type_name', 'unit_code', 'unit_name', 'unit_symbol',
        'condition_code', 'condition_name', 'price_per_unit', 'subtotal',
        'rounding_version', 'price_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'weight_kg' => 'decimal:3',
            'price_per_unit' => 'integer',
            'subtotal' => 'integer',
            'price_snapshot' => 'array',
        ];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
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

    protected static function booted(): void
    {
        self::updating(static function (self $item): void {
            if ($item->deposit?->isFinal() || $item->deposit?->isPendingReview()) {
                throw new LogicException('Final deposit items are immutable.');
            }
        });

        self::deleting(static function (self $item): void {
            if ($item->deposit?->isFinal() || $item->deposit?->isPendingReview()) {
                throw new LogicException('Final deposit items are immutable.');
            }
        });
    }
}
