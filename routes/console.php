<?php

declare(strict_types=1);

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function (): void {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('operations:expire')
    ->everyFiveMinutes()
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(10);

Schedule::command('operations:purge')
    ->dailyAt('02:15')
    ->timezone('Asia/Jakarta')
    ->withoutOverlapping(30);

if (config('queue.default') === 'database' && config('operations.queue.worker_mode') === 'oneshot') {
    $databaseQueue = config('queue.connections.database');
    $maxJobs = max(1, (int) ($databaseQueue['max_jobs'] ?? 25));
    $maxTime = max(1, (int) ($databaseQueue['max_time'] ?? 55));
    $tries = max(1, (int) ($databaseQueue['tries'] ?? 3));

    Schedule::command("queue:work database --stop-when-empty --max-jobs={$maxJobs} --max-time={$maxTime} --tries={$tries}")
        ->everyMinute()
        ->timezone('Asia/Jakarta')
        ->withoutOverlapping(2);
}
