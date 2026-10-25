<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Telegram\Models\TelegramLink;
use Illuminate\Support\Facades\Log;
use Throwable;

final class QueueTelegramNotification
{
    public function handle(NotificationRequested $event): void
    {
        if (! TelegramAccess::configured()) {
            return;
        }
        try {
            $link = TelegramLink::query()->where('user_id', $event->payload->recipientId)->first();
            if ($link !== null) {
                $this->enqueue($link, $event->payload->title, $event->payload->body, $event->payload->reference, $event->payload->dedupeKey, $event->payload->scheduledAt);
            }
        } catch (Throwable $error) {
            // Delivery failures must not propagate into a committed web transaction.
            Log::warning('Telegram notification enqueue failed; scheduled recovery will inspect stored notifications.', ['code' => class_basename($error)]);
        }
    }

    public function enqueue(TelegramLink $link, string $title, string $body, string $reference, string $dedupe, ?\DateTimeInterface $at = null): void
    {
        if (! $link->transaction_notifications || $link->blocked_at !== null) {
            return;
        }
        $actor = app(TelegramAccess::class)->actor($link);
        $payload = ['kind' => 'text', 'text' => $title."\n".$body];
        if (preg_match('~^/warga/(setoran|pencairan|penjemputan|sembako)/(\d+)(?:/|$)~', $reference, $match)) {
            $payload['guards'] = [['kind' => ['setoran' => 'deposit', 'pencairan' => 'cash', 'penjemputan' => 'pickup', 'sembako' => 'grocery'][$match[1]], 'id' => (int) $match[2]]];
        } elseif (! $actor->hasRole('warga')) {
            $payload['text'] = $title."\nAda informasi layanan baru. Buka /menu untuk memeriksa data yang masih dalam hak akses Anda.";
        }
        $message = app(TelegramUi::class)->queue($link->telegram_id, $link, 'notification:'.$link->id.':'.hash('sha256', $dedupe), $payload, 'transaction_notifications');
        if ($at !== null && $message->status === 'pending') {
            $message->forceFill(['available_at' => $at])->save();
        }
    }
}
