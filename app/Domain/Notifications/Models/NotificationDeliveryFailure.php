<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable $last_attempted_at
 * @property CarbonImmutable|null $retry_after
 */
final class NotificationDeliveryFailure extends Model
{
    protected $fillable = ['dedupe_key', 'recipient_id', 'type', 'attempts', 'last_error', 'last_attempted_at', 'retry_after'];

    protected function casts(): array
    {
        return ['attempts' => 'integer', 'last_attempted_at' => 'immutable_datetime', 'retry_after' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }
}
