<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $telegram_id
 * @property string|null $link_id
 * @property string $state
 * @property array<string, mixed> $data
 * @property CarbonImmutable $expires_at
 */
final class TelegramConversation extends Model
{
    protected $primaryKey = 'telegram_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['data'];

    protected function casts(): array
    {
        return ['data' => 'encrypted:array', 'expires_at' => 'immutable_datetime'];
    }
}
