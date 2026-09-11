<?php

declare(strict_types=1);

namespace App\Domain\Corrections\Models;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

final class TransactionReversal extends Model
{
    protected $fillable = [
        'reversal_number', 'original_deposit_id', 'original_entry_id', 'reason', 'created_by', 'finalized_at',
    ];

    protected function casts(): array
    {
        return ['finalized_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Deposit, $this> */
    public function originalDeposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class, 'original_deposit_id');
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function originalEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'original_entry_id');
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'attachable');
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Transaction reversals are append-only.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Transaction reversals are append-only.');
        });
    }
}
