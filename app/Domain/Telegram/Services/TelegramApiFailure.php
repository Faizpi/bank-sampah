<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use RuntimeException;

final class TelegramApiFailure extends RuntimeException
{
    public readonly string $description;

    public function __construct(
        public readonly string $errorCode,
        public readonly int $retryAfter = 0,
        public readonly bool $uncertain = false,
        public readonly bool $blocked = false,
        string $description = '',
    ) {
        // Never retain the HTTP exception: its URL contains the bot credential.
        $this->description = self::safeDescription($description);
        parent::__construct($errorCode);
    }

    public static function safeDescription(mixed $value): string
    {
        if (! is_string($value)) {
            return '';
        }
        $text = strip_tags(html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        $text = preg_replace('/[\x00-\x1F\x7F]/u', ' ', $text) ?? '';
        $text = self::redactSecrets($text);
        $text = preg_replace_callback('~https?://[^\s<>"\x27]+~iu', static fn (array $match): string => self::safeUrl($match[0]), $text) ?? '';
        $text = preg_replace('/\b(secret_token|token|password|authorization)\s*[:=]\s*[^\s,;]+/iu', '$1=[disamarkan]', $text) ?? '';

        return mb_substr(trim(preg_replace('/\s+/u', ' ', $text) ?? ''), 0, 500);
    }

    public static function safeUrl(string $url): string
    {
        $parts = parse_url($url);
        if ($parts === false || ! isset($parts['scheme'], $parts['host']) || ! in_array(strtolower($parts['scheme']), ['https', 'http'], true)) {
            return '[alamat tidak valid]';
        }
        // Display the destination, never user-info, query credentials, or fragments.
        $safe = $parts['scheme'].'://'.$parts['host'].(isset($parts['port']) ? ':'.$parts['port'] : '').($parts['path'] ?? '');
        $safe = preg_replace('/[\x00-\x20\x7F]/u', '', strip_tags($safe)) ?? '';

        return mb_substr(self::redactSecrets($safe), 0, 500);
    }

    private static function redactSecrets(string $text): string
    {
        foreach (['telegram.bot_token', 'telegram.webhook_secret'] as $key) {
            $secret = (string) config($key);
            if ($secret !== '') {
                $text = str_ireplace(array_unique([$secret, rawurlencode($secret), urlencode($secret)]), '[disamarkan]', $text);
            }
        }

        return preg_replace('/(?:bot)?\d{5,}(?::|%3a)[A-Za-z0-9_-]{5,}/i', '[token disamarkan]', $text) ?? '';
    }
}
