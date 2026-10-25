<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $telegram_id
 * @property string|null $link_id
 * @property bool $private
 * @property string $category
 * @property array<string, mixed> $payload
 * @property string $status
 * @property int $attempts
 * @property string|null $lease
 * @property CarbonImmutable $available_at
 */
final class TelegramOutbox extends Model
{
    protected $table = 'telegram_outbox';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['payload'];

    protected function casts(): array
    {
        return ['payload' => 'encrypted:array', 'private' => 'boolean', 'attempts' => 'integer', 'available_at' => 'immutable_datetime', 'leased_at' => 'immutable_datetime', 'sent_at' => 'immutable_datetime'];
    }
}
