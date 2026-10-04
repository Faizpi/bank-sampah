<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Shared\InvalidValue;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

final class BalanceHold extends Model
{
    public const STATUS_ACTIVE = 'aktif';

    public const STATUS_CONVERTED = 'dikonversi';

    public const STATUS_RELEASED = 'dilepas';

    protected $fillable = [
        'hold_number', 'ledger_account_id', 'source_type', 'source_id', 'source_key',
        'amount', 'status', 'held_at', 'released_at', 'converted_at',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'held_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
            'converted_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    public function isActive(): bool
    {
        return $this->status === self::STATUS_ACTIVE;
    }

    protected static function booted(): void
    {
        self::creating(static function (self $hold): void {
            if ((int) $hold->amount <= 0) {
                throw new InvalidValue('Hold harus bernilai positif.');
            }
        });

        self::updating(static function (self $hold): void {
            $immutable = ['hold_number', 'ledger_account_id', 'source_type', 'source_id', 'source_key', 'amount', 'held_at'];
            if (array_intersect($immutable, array_keys($hold->getDirty())) !== []) {
                throw new LogicException('Balance hold identity is immutable.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Balance holds are append-only status histories.');
        });
    }
}
