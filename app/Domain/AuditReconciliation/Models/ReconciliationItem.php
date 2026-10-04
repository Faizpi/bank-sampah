<?php

declare(strict_types=1);

namespace App\Domain\AuditReconciliation\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class ReconciliationItem extends Model
{
    public const STATUS_OPEN = 'terbuka';

    public const STATUS_VERIFIED = 'sesuai';

    public const STATUS_DIFFERENCE = 'selisih';

    protected $fillable = ['reconciliation_id', 'item_type', 'reference_type', 'reference_id', 'expected_total', 'actual_total', 'difference', 'status', 'note'];

    protected function casts(): array
    {
        return ['expected_total' => 'integer', 'actual_total' => 'integer', 'difference' => 'integer'];
    }

    /** @return BelongsTo<Reconciliation, $this> */
    public function reconciliation(): BelongsTo
    {
        return $this->belongsTo(Reconciliation::class);
    }
}
