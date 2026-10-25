<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\ProcessTelegramUpdate;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Models\User;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use RuntimeException;

final class TelegramHarness
{
    /** @param array<string, mixed>|\Closure $callbacks */
    public static function fakeHttp(array|\Closure $callbacks): void
    {
        Http::swap(new Factory);
        Http::preventStrayRequests();
        Http::fake($callbacks);
    }

    public static function configure(): void
    {
        config()->set(['telegram.enabled' => true, 'telegram.bot_username' => 'sindangheula_test_bot', 'telegram.bot_token' => '123456:TEST', 'telegram.webhook_secret' => str_repeat('s', 40), 'telegram.inline_processing' => false]);
        self::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]])]);
    }

    /** @param array<string, bool|string> $preferences */
    public static function link(User $actor, string $telegramId, array $preferences = []): TelegramLink
    {
        $actor->forceFill(['password' => 'TelegramOnly#2026'])->save();
        $service = app(TelegramLinkService::class);
        $start = $service->begin($actor, 'TelegramOnly#2026');
        $service->claim(TelegramChallenge::findOrFail($start['id'])->token_hash, $telegramId, 'Telegram uji');

        return $service->approve($actor, $start['id'], array_merge(['transaction_notifications' => false, 'reminders' => false, 'summaries' => false, 'announcements' => false, 'summary_frequency' => 'weekly'], $preferences));
    }

    /** @param array<string, mixed> $payload */
    public static function update(string $telegramId, array $payload): TelegramUpdate
    {
        $id = max(1000, (int) TelegramUpdate::query()->max('id') + 1);
        $update = TelegramUpdate::query()->create(['id' => $id, 'telegram_id' => $telegramId, 'payload' => $payload, 'payload_hash' => hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR))]);
        app(ProcessTelegramUpdate::class)->handle($id);
        $update->refresh();
        if ($update->status !== 'processed') {
            throw new RuntimeException('Update failed: '.$update->getAttribute('error_code').' for '.json_encode($payload));
        }

        return $update;
    }

    public static function text(string $telegramId, string $text): TelegramUpdate
    {
        return self::update($telegramId, ['text' => $text]);
    }

    public static function button(string $telegramId, string $label): string
    {
        foreach (TelegramOutbox::query()->where('telegram_id', $telegramId)->orderByDesc('sequence')->get() as $message) {
            foreach ($message->payload['reply_markup']['inline_keyboard'] ?? [] as $row) {
                foreach ($row as $button) {
                    if ($button['text'] === $label && isset($button['callback_data'])) {
                        return $button['callback_data'];
                    }
                }
            }
        }
        throw new RuntimeException('Missing button: '.$label.'. Last reply: '.self::lastText($telegramId));
    }

    public static function click(string $telegramId, string $label): TelegramUpdate
    {
        return self::update($telegramId, ['callback' => self::button($telegramId, $label), 'callback_id' => 'test-callback']);
    }

    public static function lastText(string $telegramId): string
    {
        $message = TelegramOutbox::query()->where('telegram_id', $telegramId)->where('kind', 'text')->orderByDesc('sequence')->first();

        return (string) ($message?->payload['text'] ?? '');
    }
}
