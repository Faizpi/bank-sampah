<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property CarbonImmutable|null $expires_at
 */
final class DatabaseSession extends Model
{
    protected $table = 'sessions';

    protected $keyType = 'string';

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = ['*'];

    protected $hidden = ['payload', 'ip_address', 'ip_address_hash', 'user_agent'];

    protected function casts(): array
    {
        return ['last_activity' => 'integer', 'expires_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
