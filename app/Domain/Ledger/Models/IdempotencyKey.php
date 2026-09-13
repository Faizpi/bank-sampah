<?php

declare(strict_types=1);

namespace App\Domain\Ledger\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\UniqueConstraintViolationException;
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

    /**
     * Atomically acquire or create an idempotency key, recovering from concurrent unique constraint collisions.
     *
     * @return array{key: self, is_new: bool}
     */
    public static function acquireOrCreate(int $actorId, string $scope, string $key, string $payloadHash): array
    {
        $existing = self::activeForUpdate($actorId, $scope, $key);
        if ($existing !== null) {
            return ['key' => $existing, 'is_new' => false];
        }

        try {
            /** @var self $created */
            $created = self::query()->createOrFirst(
                ['actor_id' => $actorId, 'scope' => $scope, 'key' => $key],
                ['payload_hash' => $payloadHash, 'status' => 'processing']
            );

            if (! $created->wasRecentlyCreated) {
                $existing = self::activeForUpdate($actorId, $scope, $key) ?? $created;

                return ['key' => $existing, 'is_new' => false];
            }

            return ['key' => $created, 'is_new' => true];
        } catch (UniqueConstraintViolationException) {
            for ($attempt = 0; $attempt < 5; $attempt++) {
                $existing = self::activeForUpdate($actorId, $scope, $key);
                if ($existing !== null && ($existing->result_id !== null || $existing->payload_hash !== $payloadHash)) {
                    return ['key' => $existing, 'is_new' => false];
                }
                usleep(25_000);
            }

            $existing = self::activeForUpdate($actorId, $scope, $key);
            if ($existing !== null) {
                return ['key' => $existing, 'is_new' => false];
            }

            /** @var self $created */
            $created = self::query()->create([
                'actor_id' => $actorId,
                'scope' => $scope,
                'key' => $key,
                'payload_hash' => $payloadHash,
                'status' => 'processing',
            ]);

            return ['key' => $created, 'is_new' => true];
        }
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
