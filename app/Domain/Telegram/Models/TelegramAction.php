<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $telegram_id
 * @property string|null $link_id
 * @property string|null $generation
 * @property string $command
 * @property array<string, mixed> $parameters
 * @property int|null $consumed_by
 * @property CarbonImmutable $expires_at
 */
final class TelegramAction extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['parameters'];

    protected function casts(): array
    {
        return ['parameters' => 'encrypted:array', 'expires_at' => 'immutable_datetime', 'consumed_by' => 'integer'];
    }
}
