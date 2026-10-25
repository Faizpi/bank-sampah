<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $telegram_id
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 */
final class TelegramUpdate extends Model
{
    public $incrementing = false;

    protected $guarded = [];

    protected $hidden = ['payload', 'payload_hash'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'processed_at' => 'immutable_datetime', 'attempts' => 'integer'];
    }
}
