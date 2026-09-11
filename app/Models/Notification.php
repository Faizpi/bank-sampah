<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Notifications\Support\NotificationTemplateRegistry;
use Database\Factories\NotificationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class Notification extends Model
{
    /** @use HasFactory<NotificationFactory> */
    use HasFactory;

    protected $fillable = [
        'recipient_id',
        'type',
        'title',
        'body',
        'reference',
        'read_at',
        'scheduled_at',
        'dedupe_key',
        'delivery_status', 'delivery_attempts', 'delivered_at', 'last_delivery_error',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'delivered_at' => 'immutable_datetime',
            'delivery_attempts' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function recipient(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recipient_id');
    }

    public function isAllowlisted(): bool
    {
        return in_array($this->type, NotificationTemplateRegistry::keys(), true)
            || str_starts_with($this->type, 'pickup.')
            || str_starts_with($this->type, 'withdrawal.')
            || str_starts_with($this->type, 'grocery.');
    }
}
