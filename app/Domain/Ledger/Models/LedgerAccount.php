<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

final class LedgerAccount extends Model
{
    protected $fillable = ['user_id', 'status', 'currency'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function entries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class);
    }

    /** @return HasMany<BalanceHold, $this> */
    public function holds(): HasMany
    {
        return $this->hasMany(BalanceHold::class);
    }

    public function availableBalance(): int
    {
        $incoming = (int) $this->entries()->where('direction', LedgerEntry::DIRECTION_IN)->sum('amount');
        $outgoing = (int) $this->entries()->where('direction', LedgerEntry::DIRECTION_OUT)->sum('amount');
        $held = (int) $this->holds()->where('status', BalanceHold::STATUS_ACTIVE)->sum('amount');

        return $incoming - $outgoing - $held;
    }

    protected static function booted(): void
    {
        self::deleting(static function (): void {
            throw new LogicException('Ledger accounts are historical records and cannot be deleted.');
        });
    }
}
