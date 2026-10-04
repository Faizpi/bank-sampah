<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Listeners;

use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Database\QueryException;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Log;

final class PersistNotification implements ShouldQueueAfterCommit
{
    use InteractsWithQueue;

    public string $connection = 'database';

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

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
        } catch (QueryException $exception) {
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
            Log::warning('Notification persistence failed after commit.', [
                'dedupe_key' => $event->payload->dedupeKey,
                'type' => $event->payload->type,
                'error' => $exception->getMessage(),
            ]);

            throw $exception;
        }
    }
}
