<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<int, mixed> $metrics
 * @property array<int, mixed> $dimensions
 * @property int $privacy_threshold
 */
final class StatisticPublication extends Model
{
    protected $fillable = ['publication_key', 'metrics', 'dimensions', 'privacy_threshold', 'is_active', 'approved_by', 'approved_at'];

    protected function casts(): array
    {
        return ['metrics' => 'array', 'dimensions' => 'array', 'privacy_threshold' => 'integer', 'is_active' => 'boolean', 'approved_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }
}
