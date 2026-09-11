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

    public static function activeForUpdate(int $actorId, string $scope, string $key): ?self
    {
        $record = self::query()
            ->where('actor_id', $actorId)
            ->where('scope', $scope)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
        if ($record !== null && ($record->expires_at === null || $record->expires_at->lessThanOrEqualTo(now()))) {
            $record->delete();

            return null;
        }

        return $record;
    }

    private static function retentionHours(): int
    {
        return min(8_760, max(1, (int) config('operations.retention.idempotency_key_hours', 24)));
    }

    protected static function booted(): void
    {
        self::creating(static function (self $key): void {
            $key->expires_at ??= now()->addHours(self::retentionHours());
        });

        self::updating(static function (self $key): void {
            if ($key->isDirty(['actor_id', 'scope', 'key', 'payload_hash'])) {
                throw new LogicException('Idempotency identity is immutable.');
            }
        });
    }
}
