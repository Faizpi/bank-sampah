<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Models;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property int $user_id
 * @property string $telegram_id
 * @property string $chat_id
 * @property string $account_fingerprint
 * @property string $generation
 * @property CarbonImmutable $authorized_until
 * @property CarbonImmutable|null $last_activity_at
 * @property bool $transaction_notifications
 * @property bool $reminders
 * @property bool $summaries
 * @property bool $announcements
 * @property string $summary_frequency
 * @property CarbonImmutable|null $blocked_at
 */
final class TelegramLink extends Model
{
    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected $hidden = ['account_fingerprint', 'telegram_id', 'chat_id'];

    protected function casts(): array
    {
        return ['authorized_until' => 'immutable_datetime', 'last_activity_at' => 'immutable_datetime', 'blocked_at' => 'immutable_datetime', 'transaction_notifications' => 'boolean', 'reminders' => 'boolean', 'summaries' => 'boolean', 'announcements' => 'boolean'];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
