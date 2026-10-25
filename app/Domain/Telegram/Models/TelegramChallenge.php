<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property int $user_id
 * @property string $token_hash
 * @property string $account_fingerprint
 * @property string|null $link_id
 * @property string|null $telegram_id
 * @property string|null $display_name
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $consumed_at
 * @property CarbonImmutable|null $cancelled_at
 */
final class TelegramChallenge extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['token_hash', 'account_fingerprint'];

    protected function casts(): array
    {
        return ['expires_at' => 'immutable_datetime', 'consumed_at' => 'immutable_datetime', 'cancelled_at' => 'immutable_datetime'];
    }
}
