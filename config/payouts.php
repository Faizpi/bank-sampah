<?php

declare(strict_types=1);

return [
    'enabled' => (bool) env('PAYOUT_SANDBOX_ENABLED', false),
    'preview_only' => (bool) env('PAYOUT_SANDBOX_PREVIEW', false),
    'database' => env('PAYOUT_SANDBOX_DATABASE', ''),
    'secret_key' => env('XENDIT_PAYOUT_SECRET_KEY', ''),
    'webhook_token' => env('XENDIT_PAYOUT_WEBHOOK_TOKEN', ''),
    'api_version' => '2025-09-01',
    'max_amount' => 1_000_000,
    'http_timeout' => 8,
    // Routing identifiers must be confirmed against the account's v3 dynamic schema.
    'routing' => [
        'bca' => env('XENDIT_PAYOUT_ROUTING_BCA', ''),
        'mandiri' => env('XENDIT_PAYOUT_ROUTING_MANDIRI', ''),
        'bri' => env('XENDIT_PAYOUT_ROUTING_BRI', ''),
        'bni' => env('XENDIT_PAYOUT_ROUTING_BNI', ''),
        'dana' => env('XENDIT_PAYOUT_ROUTING_DANA', ''),
        'gopay' => env('XENDIT_PAYOUT_ROUTING_GOPAY', ''),
    ],
];
