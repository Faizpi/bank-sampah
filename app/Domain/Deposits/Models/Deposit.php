<?php

declare(strict_types=1);

namespace App\Domain\Deposits\Models;

use App\Domain\Corrections\Models\TransactionCorrection;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    public const STATUS_PENDING_REVIEW = 'menunggu_persetujuan';

    public const STATUS_REJECTED = 'ditolak';

    public const STATUS_CORRECTED = 'dikoreksi';

    public const STATUS_REVERSED = 'dibalik';

    protected $fillable = [
        'deposit_number', 'customer_id', 'staff_id', 'method', 'pickup_request_id', 'mobile_service_id', 'location',
        'occurred_at', 'status', 'total_weight_kg', 'total_value', 'finalized_at', 'review_requested_at', 'reviewed_at', 'reviewed_by', 'review_reason',
        'idempotency_key', 'verification_token_hash', 'verification_token_encrypted',
    ];

    protected $hidden = ['verification_token_hash', 'verification_token_encrypted', 'idempotency_key'];

    protected function casts(): array
    {
        return [
            'occurred_at' => 'immutable_datetime',
            'finalized_at' => 'immutable_datetime',
            'review_requested_at' => 'immutable_datetime',
            'reviewed_at' => 'immutable_datetime',
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

    /** @return HasOne<TransactionCorrection, $this> */
    public function correction(): HasOne
    {
        return $this->hasOne(TransactionCorrection::class);
    }

    /** @return BelongsTo<User, $this> */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
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

    public function isPendingReview(): bool
    {
        return $this->status === self::STATUS_PENDING_REVIEW;
    }

    public function isFinal(): bool
    {
        return in_array($this->status, [self::STATUS_FINAL, self::STATUS_CORRECTED, self::STATUS_REVERSED], true);
    }

    /**
     * Nilai setoran asli tidak pernah ditimpa agar jejak audit tetap utuh.
     * Tampilan operasional memakai nilai akhir dari koreksi resmi bila ada.
     */
    public function effectiveTotalValue(): int
    {
        if ($this->status !== self::STATUS_CORRECTED) {
            return (int) $this->total_value;
        }

        $correction = $this->relationLoaded('correction')
            ? $this->getRelation('correction')
            : $this->correction()->first();

        return $correction instanceof TransactionCorrection
            ? $correction->effectiveTotalValue()
            : (int) $this->total_value;
    }

    public function getEffectiveTotalValueAttribute(): int
    {
        return $this->effectiveTotalValue();
    }

    public function verificationToken(): ?string
    {
        $value = $this->getAttribute('verification_token_encrypted');

        return is_string($value) && $value !== '' ? $value : null;
    }

    public function rotateVerificationToken(QrToken $token): void
    {
        if (! $this->isFinal() || $this->status === self::STATUS_REVERSED) {
            throw new LogicException('Hanya setoran final yang memiliki QR verifikasi aktif.');
        }

        $this->forceFill([
            'verification_token_hash' => $token->hash(),
            'verification_token_encrypted' => $token->value(),
        ])->saveQuietly();
    }

    protected static function booted(): void
    {
        self::updating(static function (self $deposit): void {
            $originalStatus = (string) $deposit->getOriginal('status');
            if ($originalStatus === '') {
                return;
            }

            $dirty = array_keys($deposit->getDirty());
            $meaningfulDirty = array_values(array_diff($dirty, ['updated_at']));
            if (in_array($originalStatus, [self::STATUS_FINAL, self::STATUS_CORRECTED, self::STATUS_REVERSED], true)
                && ($meaningfulDirty !== ['status'] || ! in_array($deposit->getAttribute('status'), [self::STATUS_CORRECTED, self::STATUS_REVERSED], true))) {
                throw new LogicException('Final deposits are immutable; use a correction or reversal.');
            }

            if ($originalStatus === self::STATUS_PENDING_REVIEW
                && ($meaningfulDirty === [] || ! in_array((string) $deposit->getAttribute('status'), [self::STATUS_FINAL, self::STATUS_REJECTED], true)
                    || array_diff($meaningfulDirty, ['status', 'finalized_at', 'reviewed_at', 'reviewed_by', 'review_reason']) !== [])) {
                throw new LogicException('Setoran yang menunggu persetujuan hanya dapat ditinjau melalui proses persetujuan resmi.');
            }
        });

        self::deleting(static function (self $deposit): void {
            if ($deposit->isFinal() || $deposit->isPendingReview()) {
                throw new LogicException('Final deposits cannot be deleted.');
            }
        });
    }
}
