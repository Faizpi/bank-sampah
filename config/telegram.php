<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('TELEGRAM_ENABLED', false),
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),
    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET', ''),
    'inline_processing' => (bool) env('TELEGRAM_INLINE_PROCESSING', true),
    'inline_messages' => (int) env('TELEGRAM_INLINE_MESSAGES', 2),
    'challenge_minutes' => 5,
    'action_minutes' => 15,
    'conversation_minutes' => 30,
    'reminder_hour' => 9,
    'http_timeout' => 5,
    'max_upload_bytes' => 5 * 1024 * 1024,
    'reminder_hours' => 24,
    'retention_days' => 7,
];
