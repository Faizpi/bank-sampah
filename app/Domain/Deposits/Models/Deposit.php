<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Models;

use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * @property int $id
 * @property int $customer_id
 * @property string $deposit_number
 * @property string $status
 * @property int|null $total_value
 * @property string|null $total_weight_kg
 * @property CarbonImmutable $occurred_at
 */
final class Deposit extends Model
{
    public const STATUS_DRAFT = 'draf';

    public const STATUS_FINAL = 'final';

    public const STATUS_CORRECTED = 'dikoreksi';

    public const STATUS_REVERSED = 'dibalik';

    protected $fillable = [
        'deposit_number', 'customer_id', 'staff_id', 'method', 'pickup_request_id', 'mobile_service_id', 'location',
        'occurred_at', 'status', 'total_weight_kg', 'total_value', 'finalized_at',
        'idempotency_key', 'verification_token_hash', 'verification_token_encrypted',
    ];

    protected $hidden = ['verification_token_hash', 'verification_token_encrypted', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
            'total_weight_kg' => 'decimal:3',
            'total_value' => 'integer',
            'verification_token_encrypted' => 'encrypted',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'staff_id');
    }

    /** @return BelongsTo<PickupRequest, $this> */
    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    /** @return BelongsTo<MobileService, $this> */
    public function mobileService(): BelongsTo
    {
        return $this->belongsTo(MobileService::class);
    }

    /** @return HasMany<DepositItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(DepositItem::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'attachable');
    }

    /** @return HasMany<LedgerEntry, $this> */
    public function ledgerEntries(): HasMany
    {
        return $this->hasMany(LedgerEntry::class, 'source_id')->where('source_type', self::class);
    }

    public function isDraft(): bool
    {
        return $this->status === self::STATUS_DRAFT;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_FINAL, self::STATUS_CORRECTED, self::STATUS_REVERSED], true);
    }

    public function verificationToken(): ?string
    {
        $value = $this->getAttribute('verification_token_encrypted');

        return is_string($value) && $value !== '' ? $value : null;
    }

    protected static function booted(): void
    {
        self::updating(static function (self $deposit): void {
            if (! $deposit->getOriginal('status') || ! in_array($deposit->getOriginal('status'), [self::STATUS_FINAL, self::STATUS_CORRECTED, self::STATUS_REVERSED], true)) {
                return;
            }

            $dirty = array_keys($deposit->getDirty());
            $meaningfulDirty = array_values(array_diff($dirty, ['updated_at']));
            if ($meaningfulDirty !== ['status'] || ! in_array($deposit->getAttribute('status'), [self::STATUS_CORRECTED, self::STATUS_REVERSED], true)) {
                throw new LogicException('Final deposits are immutable; use a correction or reversal.');
            }
        });

        self::deleting(static function (self $deposit): void {
            if ($deposit->isFinal()) {
                throw new LogicException('Final deposits cannot be deleted.');
            }
        });
    }
}
