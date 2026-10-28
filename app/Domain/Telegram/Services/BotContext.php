<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramLink;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

final class BotContext
{
    private int $sequence = 0;

    public function __construct(public readonly int $updateId, public readonly string $telegramId, public readonly ?TelegramLink $link, public readonly TelegramConversation $conversation) {}

    public function actor(?string $role = null): User
    {
        if ($this->link === null) {
            throw new AuthorizationException('Hubungkan akun melalui website terlebih dahulu.');
        }
        $actor = app(TelegramAccess::class)->actor($this->link);
        if ($role !== null && ! $actor->hasRole($role)) {
            throw new AuthorizationException('Menu ini tidak tersedia untuk peran Anda.');
        }

        return $actor;
    }

    /** @param list<array{label: string, command?: string, parameters?: array<string, mixed>, url?: string}> $buttons */
    public function say(string $text, array $buttons = []): void
    {
        foreach ($buttons as &$button) {
            if (isset($button['command']) && ! in_array($button['command'], ['menu', 'help'], true)) {
                $button['parameters']['__context'] = self::contextHash($this->conversation);
            }
        }
        unset($button);
        app(TelegramUi::class)->reply($this->telegramId, $this->link, 'reply:'.$this->updateId.':'.$this->sequence++, $text, $buttons);
    }

    public static function contextHash(TelegramConversation $conversation): string
    {
        return hash('sha256', json_encode([$conversation->link_id, $conversation->state, $conversation->data], JSON_THROW_ON_ERROR));
    }

    /** @param array<string, mixed> $parameters
     * @return array{label: string, command: string, parameters: array<string, mixed>}
     */
    public static function button(string $label, string $command, array $parameters = []): array
    {
        return ['label' => $label, 'command' => $command, 'parameters' => $parameters];
    }

    /** @param array<string, mixed> $data */
    public function state(string $state, array $data = []): void
    {
        $this->conversation->forceFill(['state' => $state, 'data' => $data, 'link_id' => $this->link?->id, 'expires_at' => now()->addMinutes((int) config('telegram.conversation_minutes'))])->save();
    }

    public function key(string $scope): string
    {
        return 'telegram:'.$scope.':'.$this->updateId;
    }

    public function connection(): TelegramLink
    {
        if ($this->link === null) {
            throw new AuthorizationException('Tautkan akun melalui website.');
        }

        return $this->link;
    }

    /** @param array<string, mixed> $data */
    public function file(array $data): void
    {
        $this->say('Berkas sedang disiapkan. Pengiriman diproses oleh antrean; tidak perlu mengulang permintaan.');
        app(TelegramUi::class)->queue($this->telegramId, $this->link, 'reply:'.$this->updateId.':'.$this->sequence++, $data);
    }
}
