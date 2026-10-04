<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Application Name
    |--------------------------------------------------------------------------
    |
    | This value is the name of your application, which will be used when the
    | framework needs to place the application's name in a notification or
    | other UI elements where an application name needs to be displayed.
    |
    */

    'name' => env('APP_NAME', 'Laravel'),

    /*
    |--------------------------------------------------------------------------
    | Application Environment
    |--------------------------------------------------------------------------
    |
    | This value determines the "environment" your application is currently
    | running in. This may determine how you prefer to configure various
    | services the application utilizes. Set this in your ".env" file.
    |
    */

    'env' => env('APP_ENV', 'production'),

    /*
    |--------------------------------------------------------------------------
    | Application Debug Mode
    |--------------------------------------------------------------------------
    |
    | When your application is in debug mode, detailed error messages with
    | stack traces will be shown on every error that occurs within your
    | application. If disabled, a simple generic error page is shown.
    |
    */

    'debug' => (bool) env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Application URL
    |--------------------------------------------------------------------------
    |
    | This URL is used by the console to properly generate URLs when using
    | the Artisan command line tool. You should set this to the root of
    | the application so that it's available within Artisan commands.
    |
    */

    'url' => env('APP_URL', 'http://localhost'),

    'terms_version' => env('TERMS_VERSION', 'v1.0'),

    // Optional first backoffice administrator, created by Database\Seeders\InitialAdminSeeder.
    // Leave unset in production unless you intend to bootstrap an admin this way.
    'initial_admin_email' => env('APP_INITIAL_ADMIN_EMAIL'),
    'initial_admin_password' => env('APP_INITIAL_ADMIN_PASSWORD'),

    // Temporary demo data switch. It defaults to enabled outside production so
    // local development remains convenient, while production must opt in
    // explicitly with both APP_DEMO_MODE=true and APP_DEMO_PASSWORD.
    'demo_mode' => env('APP_DEMO_MODE', env('APP_ENV', 'production') !== 'production'),
    'demo_password' => env('APP_DEMO_PASSWORD'),

    'registration_max_attempts_per_minute' => (int) env('REGISTRATION_MAX_ATTEMPTS_PER_MINUTE', 5),

    'public_qr_max_attempts_per_minute' => (int) env('PUBLIC_QR_MAX_ATTEMPTS_PER_MINUTE', 30),

    'public_data_max_attempts_per_minute' => (int) env('PUBLIC_DATA_MAX_ATTEMPTS_PER_MINUTE', 120),

    'upload_max_attempts_per_minute' => (int) env('UPLOAD_MAX_ATTEMPTS_PER_MINUTE', 10),

    'export_max_attempts_per_minute' => (int) env('EXPORT_MAX_ATTEMPTS_PER_MINUTE', 10),

    'financial_request_max_attempts_per_minute' => (int) env('FINANCIAL_REQUEST_MAX_ATTEMPTS_PER_MINUTE', 10),

    'pickup_booking_horizon_days' => (int) env('PICKUP_BOOKING_HORIZON_DAYS', 30),

    'withdrawal_expiry_days' => (int) env('WITHDRAWAL_EXPIRY_DAYS', 7),

    'withdrawal_minimum_amount' => (int) env('WITHDRAWAL_MINIMUM_AMOUNT', 10_000),

    // Financial guardrails. Values are evaluated server-side, never only in the form.
    'deposit_max_item_weight_kg' => (string) env('DEPOSIT_MAX_ITEM_WEIGHT_KG', '50'),

    'deposit_max_total_weight_kg' => (string) env('DEPOSIT_MAX_TOTAL_WEIGHT_KG', '100'),

    'deposit_review_threshold' => (int) env('DEPOSIT_REVIEW_THRESHOLD', 250_000),

    'grocery_expiry_days' => (int) env('GROCERY_EXPIRY_DAYS', 7),

    'statistics_privacy_threshold' => (int) env('STATISTICS_PRIVACY_THRESHOLD', 5),

    /*
    |--------------------------------------------------------------------------
    | Application Timezone
    |--------------------------------------------------------------------------
    |
    | Here you may specify the default timezone for your application, which
    | will be used by the PHP date and date-time functions. The timezone
    | is set to "UTC" by default as it is suitable for most use cases.
    |
    */

    'timezone' => env('APP_TIMEZONE', 'Asia/Jakarta'),

    /*
    |--------------------------------------------------------------------------
    | Application Locale Configuration
    |--------------------------------------------------------------------------
    |
    | The application locale determines the default locale that will be used
    | by Laravel's translation / localization methods. This option can be
    | set to any locale for which you plan to have translation strings.
    |
    */

    'locale' => env('APP_LOCALE', 'id'),

    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'id'),

    'faker_locale' => env('APP_FAKER_LOCALE', 'en_US'),

    /*
    |--------------------------------------------------------------------------
    | Encryption Key
    |--------------------------------------------------------------------------
    |
    | This key is utilized by Laravel's encryption services and should be set
    | to a random, 32 character string to ensure that all encrypted values
    | are secure. You should do this prior to deploying the application.
    |
    */

    'cipher' => 'AES-256-CBC',

    'key' => env('APP_KEY'),

    'previous_keys' => [
        ...array_filter(
            explode(',', (string) env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Maintenance Mode Driver
    |--------------------------------------------------------------------------
    |
    | These configuration options determine the driver used to determine and
    | manage Laravel's "maintenance mode" status. The "cache" driver will
    | allow maintenance mode to be controlled across multiple machines.
    |
    | Supported drivers: "file", "cache"
    |
    */

    'maintenance' => [
        'driver' => env('APP_MAINTENANCE_DRIVER', 'file'),
        'store' => env('APP_MAINTENANCE_STORE', 'database'),
    ],

];
