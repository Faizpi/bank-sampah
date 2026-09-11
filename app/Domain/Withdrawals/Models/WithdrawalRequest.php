<?php

declare(strict_types=1);

namespace App\Domain\Withdrawals\Models;

use App\Domain\CustomersRegions\Models\AssistedCustomerService;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\StatusHistory;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\WithdrawalRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $amount
 * @property WithdrawalStatus $status
 * @property int|null $balance_hold_id
 * @property int $requested_by_id
 * @property int|null $approver_id
 * @property int|null $payer_id
 * @property CarbonImmutable|null $expires_at
 * @property CarbonImmutable|null $paid_at
 */
final class WithdrawalRequest extends Model
{
    /** @use HasFactory<WithdrawalRequestFactory> */
    use HasFactory;

    protected static function newFactory(): WithdrawalRequestFactory
    {
        return WithdrawalRequestFactory::new();
    }

    protected $fillable = [
        'request_number', 'customer_id', 'rt_id', 'service_area_id', 'requested_by_id', 'amount', 'status', 'balance_hold_id', 'approver_id', 'payer_id',
        'pickup_location', 'pickup_date', 'approved_at', 'expires_at', 'paid_at', 'recipient_verification',
        'recipient_reference', 'rejection_reason', 'cancellation_reason', 'proof_media_id', 'receipt_ledger_entry_id',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'status' => WithdrawalStatus::class,
            'pickup_date' => 'immutable_date',
            'approved_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'paid_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<User, $this> */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_id');
    }

    /** @return BelongsTo<CustomerProfile, $this> */
    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_id', 'user_id');
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /** @return BelongsTo<BalanceHold, $this> */
    public function balanceHold(): BelongsTo
    {
        return $this->belongsTo(BalanceHold::class, 'balance_hold_id');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function payer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'payer_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function proofMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'proof_media_id');
    }

    /** @return BelongsTo<LedgerEntry, $this> */
    public function receiptLedgerEntry(): BelongsTo
    {
        return $this->belongsTo(LedgerEntry::class, 'receipt_ledger_entry_id');
    }

    /** @return HasOne<AssistedCustomerService, $this> */
    public function assistedService(): HasOne
    {
        return $this->hasOne(AssistedCustomerService::class, 'withdrawal_id');
    }

    /** @return MorphMany<StatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject');
    }

    public function canTransitionTo(WithdrawalStatus $next): bool
    {
        return $this->status->canTransitionTo($next);
    }

    protected static function booted(): void
    {
        self::updating(static function (self $withdrawal): void {
            $dirty = array_keys($withdrawal->getDirty());
            $immutable = ['customer_id', 'rt_id', 'service_area_id', 'requested_by_id', 'amount', 'balance_hold_id', 'request_number'];
            $holdIsBeingAttached = $withdrawal->isDirty('balance_hold_id') && $withdrawal->getOriginal('balance_hold_id') === null;
            if (array_intersect($immutable, $dirty) !== [] && ! ($holdIsBeingAttached && count(array_diff($dirty, ['balance_hold_id', 'updated_at'])) === 0)) {
                throw new LogicException('Pencairan dan nominalnya immutable setelah pengajuan.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Pengajuan pencairan adalah histori dan tidak dapat dihapus.');
        });
    }
}
