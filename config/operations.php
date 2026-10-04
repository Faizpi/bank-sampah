<?php

declare(strict_types=1);

return [
    'deployment' => [
        'release_root' => env('DEPLOYMENT_RELEASE_ROOT', base_path()),
        'document_root' => env('DEPLOYMENT_DOCUMENT_ROOT', public_path()),
        'vite_manifest' => env('VITE_MANIFEST_PATH', public_path('build/manifest.json')),
        'exposed_paths' => array_values(array_filter(explode(',', (string) env('DEPLOYMENT_EXPOSED_PATHS', '')))),
    ],
    'queue' => [
        'worker_mode' => env('OPERATIONS_QUEUE_WORKER_MODE', 'none'),
    ],
    'scheduler' => [
        'topology' => env('SCHEDULER_TOPOLOGY', 'cron'),
        'batch_size' => (int) env('OPERATIONS_SCHEDULER_BATCH_SIZE', 50),
        'heartbeat_ttl_seconds' => (int) env('OPERATIONS_SCHEDULER_HEARTBEAT_TTL_SECONDS', 172800),
        'heartbeat_freshness_seconds' => (int) env('OPERATIONS_SCHEDULER_HEARTBEAT_FRESHNESS_SECONDS', 172800),
    ],
    'retention' => [
        'notification_failure_hours' => (int) env('OPERATIONS_NOTIFICATION_FAILURE_RETENTION_HOURS', 168),
    ],
    'database_queue' => [
        'max_jobs' => (int) env('DB_QUEUE_MAX_JOBS', 25),
        'max_time' => (int) env('DB_QUEUE_MAX_TIME', 55),
        'tries' => (int) env('DB_QUEUE_TRIES', 3),
    ],
    'health' => [
        'backup_max_age_hours' => (int) env('OPERATIONS_HEALTH_BACKUP_MAX_AGE_HOURS', 24),
        'queue_backlog_threshold' => (int) env('OPERATIONS_HEALTH_QUEUE_BACKLOG_THRESHOLD', 100),
    ],
];
