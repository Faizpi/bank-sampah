<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property CarbonImmutable|null $expires_at
 */
final class IdempotencyKey extends Model
{
    protected $fillable = [
        'actor_id', 'scope', 'key', 'payload_hash', 'status', 'result_type', 'result_id', 'expires_at',
    ];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'result_id' => 'integer'];
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    protected static function booted(): void
    {
        self::updating(static function (self $key): void {
            if ($key->isDirty(['actor_id', 'scope', 'key', 'payload_hash'])) {
                throw new LogicException('Idempotency identity is immutable.');
            }
        });
    }
}
