<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Throwable;

final class PersistNotification
{
    public function handle(NotificationRequested $event): void
    {
        try {
            Notification::query()->firstOrCreate(
                ['dedupe_key' => $event->payload->dedupeKey],
                array_merge($event->payload->toArray(), [
                    'delivery_status' => 'delivered',
                    'delivery_attempts' => 1,
                    'delivered_at' => now(),
                ]),
            );
            NotificationDeliveryFailure::query()->where('dedupe_key', $event->payload->dedupeKey)->delete();
        } catch (Throwable $exception) {
            $this->recordFailure($event, $exception);
        }
    }

    private function recordFailure(NotificationRequested $event, Throwable $exception): void
    {
        try {
            if (Notification::query()->where('dedupe_key', $event->payload->dedupeKey)->exists()) {
                return;
            }

            NotificationDeliveryFailure::query()->updateOrCreate(
                ['dedupe_key' => $event->payload->dedupeKey],
                [
                    'recipient_id' => User::query()->whereKey($event->payload->recipientId)->exists() ? $event->payload->recipientId : null,
                    'type' => $event->payload->type,
                    'attempts' => (int) (NotificationDeliveryFailure::query()->where('dedupe_key', $event->payload->dedupeKey)->value('attempts') ?? 0) + 1,
                    'last_error' => $exception->getMessage(),
                    'last_attempted_at' => now(),
                    'retry_after' => now()->addSeconds(10),
                ],
            );
        } catch (Throwable $recordingException) {
            $this->logFailure('Notification failure could not be recorded after commit.', $event, $recordingException);
        }

        $this->logFailure('Notification persistence failed after commit.', $event, $exception);
    }

    private function logFailure(string $message, NotificationRequested $event, Throwable $exception): void
    {
        try {
            Log::warning($message, [
                'dedupe_key' => $event->payload->dedupeKey,
                'type' => $event->payload->type,
                'error' => $exception->getMessage(),
            ]);
        } catch (Throwable) {
            // Notification delivery must never affect a committed business transaction.
        }
    }
}
