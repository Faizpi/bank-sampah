<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Telegram\Models\TelegramAction;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use Illuminate\Support\Str;

final class TelegramUi
{
    /** @param list<array{label: string, command?: string, parameters?: array<string, mixed>, url?: string}> $buttons */
    public function reply(string $telegramId, ?TelegramLink $link, string $key, string $text, array $buttons = []): TelegramOutbox
    {
        $keyboard = [];
        foreach ($buttons as $button) {
            if (isset($button['url'])) {
                $keyboard[] = ['text' => $button['label'], 'url' => $button['url']];

                continue;
            }
            $nonce = bin2hex(random_bytes(16));
            TelegramAction::query()->create([
                'id' => hash('sha256', $nonce), 'telegram_id' => $telegramId, 'link_id' => $link?->id,
                'generation' => $link?->generation, 'command' => $button['command'], 'parameters' => $button['parameters'] ?? [],
                'expires_at' => now()->addMinutes((int) config('telegram.action_minutes')),
            ]);
            $keyboard[] = ['text' => $button['label'], 'callback_data' => 'a:'.$nonce];
        }
        $payload = ['kind' => 'text', 'text' => mb_substr($text, 0, 4000)];
        if ($keyboard !== []) {
            $payload['reply_markup'] = ['inline_keyboard' => array_chunk($keyboard, 2)];
        }

        return $this->queue($telegramId, $link, $key, $payload);
    }

    /** @param array<string, mixed> $payload */
    public function queue(string $telegramId, ?TelegramLink $link, string $key, array $payload, string $category = 'reply'): TelegramOutbox
    {
        if ($payload['kind'] === 'text') {
            $payload['text'] = mb_substr((string) $payload['text'], 0, 4000);
        }

        return TelegramOutbox::query()->firstOrCreate(['dedupe_key' => $key], [
            'id' => (string) Str::uuid(), 'telegram_id' => $telegramId, 'link_id' => $link?->id,
            'private' => $link !== null, 'generation' => $link?->generation, 'category' => $category, 'kind' => $payload['kind'], 'payload' => $payload, 'available_at' => now(),
        ]);
    }
}
