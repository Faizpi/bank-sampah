<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Models;

use App\Domain\AuditReconciliation\Enums\ReconciliationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;

/**
 * @property ReconciliationStatus $status
 * @property CarbonImmutable $business_date
 * @property int $created_by
 * @property int|null $service_area_id
 * @property string $scope_key
 * @property int|null $approver_id
 * @property int|null $rejector_id
 * @property int $difference
 * @property int $closing_total
 * @property string|null $notes
 */
final class Reconciliation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'status' => ReconciliationStatus::class,
            'opening_total' => 'integer',
            'deposit_total' => 'integer',
            'withdrawal_total' => 'integer',
            'grocery_total' => 'integer',
            'hold_total' => 'integer',
            'closing_total' => 'integer',
            'difference' => 'integer',
            'submitted_at' => 'immutable_datetime',
            'approved_at' => 'immutable_datetime',
            'rejected_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approver_id');
    }

    /** @return BelongsTo<User, $this> */
    public function rejector(): BelongsTo
    {
        return $this->belongsTo(User::class, 'rejector_id');
    }

    /** @return BelongsTo<self, $this> */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** @return HasMany<ReconciliationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
    }

    public function hasOpenDiscrepancy(): bool
    {
        if ($this->difference === 0) {
            return false;
        }
        $latest = $this->items()->where('item_type', 'cash_difference')->latest('id')->first();

        return $latest === null || $latest->status->value !== 'diselesaikan';
    }

    protected static function booted(): void
    {
        self::updating(static function (self $record): void {
            $dirty = array_keys($record->getDirty());
            if (array_diff($dirty, ['status', 'approver_id', 'rejector_id', 'submitted_at', 'approved_at', 'rejected_at', 'notes', 'parent_id', 'updated_at']) !== []) {
                throw new LogicException('Reconciliation snapshots are append-only; create a new version.');
            }
        });

        self::deleting(static function (): void {
            throw new LogicException('Reconciliations are append-only revisions.');
        });
    }
}
