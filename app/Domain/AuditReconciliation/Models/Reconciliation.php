<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Reconciliation extends Model
{
    public const STATUS_DRAFT = 'draf';

    public const STATUS_SUBMITTED = 'diajukan';

    public const STATUS_APPROVED = 'disetujui';

    public const STATUS_REJECTED = 'ditolak';

    protected $fillable = [
        'uuid', 'business_date', 'service_area_id', 'scope_key', 'status', 'version', 'parent_id',
        'opening_total', 'deposit_total', 'withdrawal_total', 'grocery_total', 'hold_total', 'cash_total', 'closing_total', 'difference',
        'notes', 'created_by', 'approver_id', 'rejector_id', 'submitted_at', 'approved_at', 'rejected_at',
    ];

    protected function casts(): array
    {
        return [
            'business_date' => 'immutable_date',
            'opening_total' => 'integer', 'deposit_total' => 'integer', 'withdrawal_total' => 'integer', 'grocery_total' => 'integer',
            'hold_total' => 'integer', 'cash_total' => 'integer', 'closing_total' => 'integer', 'difference' => 'integer',
            'submitted_at' => 'immutable_datetime', 'approved_at' => 'immutable_datetime', 'rejected_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<ReconciliationItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(ReconciliationItem::class);
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
}
