<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Models;

use App\Domain\AuditReconciliation\Enums\ReconciliationItemStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property ReconciliationItemStatus $status
 */
final class ReconciliationItem extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['expected_total' => 'integer', 'actual_total' => 'integer', 'difference' => 'integer', 'status' => ReconciliationItemStatus::class];
    }

    /** @return BelongsTo<Reconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Reconciliation items are append-only.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Reconciliation items are append-only.');
        });
    }
}
