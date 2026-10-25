<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\ProcessTelegramUpdate;
use App\Domain\Telegram\Services\SendTelegramOutbox;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramUi;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class TelegramWebhookController
{
    public function __invoke(Request $request): JsonResponse
    {
        abort_unless(TelegramAccess::configured(), 404);
        abort_unless(hash_equals((string) config('telegram.webhook_secret'), (string) $request->header('X-Telegram-Bot-Api-Secret-Token')), 403);
        abort_if(strlen($request->getContent()) > 128 * 1024, 413);
        $data = $request->json()->all();
        $id = $data['update_id'] ?? null;
        abort_unless(is_int($id) && $id >= 0, 422);
        $callback = $data['callback_query'] ?? null;
        $message = is_array($callback) ? ($callback['message'] ?? []) : ($data['message'] ?? []);
        $from = is_array($callback) ? ($callback['from'] ?? []) : ($message['from'] ?? []);
        $sender = $from['id'] ?? null;
        if (! is_int($sender) || $sender < 1 || ($from['is_bot'] ?? false) || ($message['chat']['type'] ?? null) !== 'private' || ($message['chat']['id'] ?? null) !== $sender) {
            return response()->json(['ok' => true]);
        }
        $telegramId = (string) $sender;
        $rateKey = 'telegram-inbound:'.$telegramId;
        if (RateLimiter::tooManyAttempts($rateKey, 40)) {
            return response()->json(['ok' => true]);
        }
        RateLimiter::hit($rateKey, 60);
        $text = is_string($message['text'] ?? null) ? mb_substr($message['text'], 0, 1000) : '';
        $payload = ['text' => $text, 'name' => mb_substr((string) ($from['first_name'] ?? ''), 0, 120)];
        if (preg_match('/^\/start(?:@[A-Za-z0-9_]+)? link_([A-Za-z0-9_-]{43})$/', $text, $matches) === 1) {
            $payload = ['link_hash' => hash('sha256', $matches[1]), 'name' => $payload['name']];
        } elseif (is_array($callback)) {
            $payload = ['callback' => mb_substr((string) ($callback['data'] ?? ''), 0, 64), 'callback_id' => mb_substr((string) ($callback['id'] ?? ''), 0, 100)];
        } elseif (isset($message['photo']) && is_array($message['photo'])) {
            $photo = end($message['photo']);
            $payload = ['photo' => is_array($photo) ? mb_substr((string) ($photo['file_id'] ?? ''), 0, 255) : ''];
        }
        // Store only supported fields; link credentials are hashed before persistence.
        TelegramUpdate::query()->firstOrCreate(['id' => $id], ['telegram_id' => $telegramId, 'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))]);
        if (isset($payload['photo'])) {
            app(TelegramUi::class)->reply($telegramId, null, 'photo-received:'.$id, 'Foto diterima untuk diperiksa. Tunggu hasilnya sebelum melanjutkan pengisian.');
        }
        if (config('telegram.inline_processing')) {
            app(ProcessTelegramUpdate::class)->chat($telegramId, 5, false);
            app(SendTelegramOutbox::class)->send((int) config('telegram.inline_messages'), $telegramId, false);
        }

        return response()->json(['ok' => true]);
    }
}
