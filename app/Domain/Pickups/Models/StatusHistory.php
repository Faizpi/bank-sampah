<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use LogicException;

final class StatusHistory extends Model
{
    public $timestamps = false;

    protected $fillable = ['subject_type', 'subject_id', 'old_status', 'new_status', 'actor_id', 'reason', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'immutable_datetime'];
    }

    /** @return MorphTo<Model, $this> */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    protected static function booted(): void
    {
        self::updating(static function (): void {
            throw new LogicException('Pickup status history is append-only.');
        });

        self::deleting(static function (): void {
            throw new LogicException('Pickup status history is append-only.');
        });
    }
}
