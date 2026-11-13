<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Models;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\StatusHistory;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use App\Support\HasTransactionRouteReference;
use Carbon\CarbonImmutable;
use Database\Factories\GroceryRedemptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use LogicException;

/**
 * @property string|null $settlement_payload_hash
 * @property string|null $exception_resolution
 * @property CarbonImmutable|null $exception_reported_at
 * @property CarbonImmutable|null $exception_resolved_at
 * @property int $id
 * @property int $customer_id
 * @property int $requested_by_id
 * @property int $grocery_package_id
 * @property int $value_snapshot
 * @property array<string, mixed> $package_snapshot
 * @property GroceryStatus $status
 * @property int|null $balance_hold_id
 * @property int|null $claimed_by_id
 * @property int|null $handover_actor_id
 * @property CarbonImmutable|null $handed_over_at
 */
final class GroceryRedemption extends Model
{
    /** @use HasFactory<GroceryRedemptionFactory> */
    use HasFactory;

    use HasTransactionRouteReference;

    protected static function newFactory(): GroceryRedemptionFactory
    {
        return GroceryRedemptionFactory::new();
    }

    protected $fillable = [
        'exception_kind',
        'physical_handed_over_at',
        'cancellation_requested_at', 'cancellation_request_reason',
        'claimed_at', 'claim_released_at', 'exception_reported_at', 'exception_reason', 'exception_resolved_by_id', 'exception_resolved_at', 'exception_resolution', 'settlement_payload_hash',
        'request_number', 'customer_id', 'rt_id', 'service_area_id', 'requested_by_id', 'grocery_package_id', 'value_snapshot', 'package_snapshot', 'status',
        'balance_hold_id', 'claimed_by_id', 'handover_actor_id', 'proof_media_id', 'receipt_ledger_entry_id',
        'availability_note', 'rejection_reason', 'cancellation_reason', 'ready_at', 'handed_over_at',

    ];

    protected function casts(): array
    {
        return [
            'physical_handed_over_at' => 'immutable_datetime',
            'cancellation_requested_at' => 'immutable_datetime',
            'claimed_at' => 'immutable_datetime', 'claim_released_at' => 'immutable_datetime', 'exception_reported_at' => 'immutable_datetime', 'exception_resolved_at' => 'immutable_datetime',
            'value_snapshot' => 'integer',
            'package_snapshot' => 'array',
            'status' => GroceryStatus::class,
            'ready_at' => 'immutable_datetime',
            'handed_over_at' => 'immutable_datetime',
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

    /** @return BelongsTo<GroceryPackage, $this> */
    public function package(): BelongsTo
    {
        return $this->belongsTo(GroceryPackage::class, 'grocery_package_id');
    }

    /** @return BelongsTo<BalanceHold, $this> */
    public function balanceHold(): BelongsTo
    {
        return $this->belongsTo(BalanceHold::class, 'balance_hold_id');
    }

    /** @return BelongsTo<User, $this> */

    /** @return BelongsTo<User, $this> */
    public function claimant(): BelongsTo
    {
        return $this->belongsTo(User::class, 'claimed_by_id');
    }

    /** @return BelongsTo<User, $this> */
    public function handoverActor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handover_actor_id');
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

    /** @return MorphMany<StatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject');
    }

    public function canTransitionTo(GroceryStatus $next): bool
    {
        return $this->status->canTransitionTo($next);
    }

    protected static function booted(): void
    {
        self::updating(static function (self $redemption): void {
            $dirty = array_keys($redemption->getDirty());
            $immutable = ['customer_id', 'rt_id', 'service_area_id', 'requested_by_id', 'grocery_package_id', 'value_snapshot', 'package_snapshot', 'request_number'];
            $holdIsBeingAttached = $redemption->isDirty('balance_hold_id') && $redemption->getOriginal('balance_hold_id') === null;
            if (array_intersect($immutable, $dirty) !== [] && ! ($holdIsBeingAttached && count(array_diff($dirty, ['balance_hold_id', 'updated_at'])) === 0)) {
                throw new LogicException('Snapshot dan identitas penukaran sembako immutable setelah pengajuan.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Penukaran sembako adalah histori dan tidak dapat dihapus.');
        });
    }
}
