<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
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
        if (preg_match('~^/warga/(setoran|pencairan|penjemputan|sembako|pencairan-digital)/([A-Za-z0-9_-]+)(?:/|$)~', $reference, $match)) {
            $routes = [
                'setoran' => [Deposit::class, 'deposit_number', 'deposit'],
                'pencairan' => [WithdrawalRequest::class, 'request_number', 'cash'],
                'penjemputan' => [PickupRequest::class, 'request_number', 'pickup'],
                'sembako' => [GroceryRedemption::class, 'request_number', 'grocery'],
                'pencairan-digital' => [SandboxPayout::class, 'request_number', 'payout'],
            ];
            [$model, $referenceColumn, $kind] = $routes[$match[1]];
            $recordQuery = $model::query()->where('customer_id', $link->user_id);
            $record = ctype_digit($match[2])
                ? $recordQuery->whereKey((int) $match[2])->first()
                : $recordQuery->where($referenceColumn, $match[2])->first();
            if ($record === null) {
                return;
            }
            $payload['guards'] = [['kind' => $kind, 'id' => (int) $record->getKey()]];
        } elseif (! $actor->hasRole('warga')) {
            $payload['text'] = $title."\nAda informasi layanan baru. Buka /menu untuk memeriksa data yang masih dalam hak akses Anda.";
        }
        $message = app(TelegramUi::class)->queue($link->telegram_id, $link, 'notification:'.$link->id.':'.hash('sha256', $dedupe), $payload, 'transaction_notifications');
        if ($at !== null && $message->status === 'pending') {
            $message->forceFill(['available_at' => $at])->save();
        }
    }
}
