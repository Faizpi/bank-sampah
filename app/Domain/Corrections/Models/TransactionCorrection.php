<?php

declare(strict_types=1);

namespace App\Domain\Corrections\Models;

use App\Domain\Deposits\Models\Deposit;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class TransactionCorrection extends Model
{
    protected $fillable = [
        'correction_number', 'deposit_id', 'reason', 'before_values', 'after_values',
        'delta_value', 'status', 'created_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return [
            'before_values' => 'array',
            'after_values' => 'array',
            'delta_value' => 'integer',
            'finalized_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Transaction corrections are append-only.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Transaction corrections are append-only.');
        });
    }
}
