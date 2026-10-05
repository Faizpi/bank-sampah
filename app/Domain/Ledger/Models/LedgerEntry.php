<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Domain\Shared\InvalidValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CarbonImmutable $effective_at
 */
final class LedgerEntry extends Model
{
    public const DIRECTION_IN = 'masuk';

    public const DIRECTION_OUT = 'keluar';

    public const KIND_DEPOSIT = 'deposit';

    public const KIND_CORRECTION = 'correction';

    public const KIND_REVERSAL = 'reversal';

    public const KIND_ADJUSTMENT = 'adjustment';

    protected $fillable = [
        'entry_number', 'ledger_account_id', 'direction', 'kind', 'amount',
        'source_type', 'source_id', 'source_key', 'related_entry_id',
        'effective_at', 'balance_after',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_after' => 'integer',
            'effective_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<LedgerAccount, $this> */
    public function account(): BelongsTo
    {
        return $this->belongsTo(LedgerAccount::class, 'ledger_account_id');
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function relatedEntry(): BelongsTo
    {
        return $this->belongsTo(self::class, 'related_entry_id');
    }

    protected static function booted(): void
    {
        self::creating(static function (self $entry): void {
            if ((int) $entry->amount <= 0) {
                throw new InvalidValue('Mutasi saldo harus lebih dari nol.');
            }
        });

        self::updating(static function (self $entry): void {
            if ($entry->getDirty() !== [] && array_diff(array_keys($entry->getDirty()), ['related_entry_id']) !== []) {
                throw new LogicException('Ledger entries are append-only.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Ledger entries are append-only.');
        });
    }
}
