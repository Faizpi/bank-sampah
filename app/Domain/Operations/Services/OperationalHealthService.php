<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

final readonly class OperationalHealthService
{
    public function __construct(
        private PrivateStorageBoundaryValidator $privateStorageBoundary,
        private OperationalSettingsService $settings,
    ) {}

    public function check(): OperationalHealthResult
    {
        return new OperationalHealthResult([
            'database' => $this->database(),
            'private_storage' => $this->privateStorage(),
            'scheduler' => $this->scheduler(),
            'queue' => $this->queue(),
        ]);
    }

    private function database(): OperationalHealthCheck
    {
        try {
            DB::connection()->select('select 1');

            return OperationalHealthCheck::ok();
        } catch (Throwable) {
            return OperationalHealthCheck::degraded('database_unavailable');
        }
    }

    private function privateStorage(): OperationalHealthCheck
    {
        $result = $this->privateStorageBoundary->validate();
        if (! $result->isValid()) {
            return OperationalHealthCheck::degraded($result->reasonCode());
        }

        return OperationalHealthCheck::ok(['mode' => 'local']);
    }

    private function scheduler(): OperationalHealthCheck
    {
        try {
            $queueDriver = (string) config('queue.default', 'sync');
            $workerMode = (string) config('operations.queue.worker_mode', 'none');
            $topology = (string) config('operations.scheduler.topology', 'cron');
            $configurationValid = in_array($topology, ['cron', 'cron-oneshot'], true)
                && (($queueDriver === 'sync' && $workerMode === 'none')
                    || ($queueDriver === 'database' && $workerMode === 'oneshot'));

            if (! $configurationValid) {
                return OperationalHealthCheck::degraded('scheduler_configuration_invalid');
            }

            $schedule = app(Schedule::class);
            $commands = ['operations:expire', 'operations:purge'];
            if ($queueDriver === 'database') {
                $commands[] = 'queue:work database';
            }

            foreach ($commands as $command) {
                if (! $this->scheduleContains($schedule, $command)) {
                    return OperationalHealthCheck::degraded('scheduler_command_missing');
                }
            }

            $heartbeat = app(SchedulerHeartbeat::class);
            foreach ([SchedulerHeartbeat::EXPIRE_KEY, SchedulerHeartbeat::PURGE_KEY] as $key) {
                if (! $heartbeat->exists($key)) {
                    return OperationalHealthCheck::degraded('scheduler_heartbeat_missing');
                }

                if (! $heartbeat->isFresh($key)) {
                    return OperationalHealthCheck::degraded('scheduler_heartbeat_stale');
                }
            }

            return OperationalHealthCheck::ok(['topology' => $topology]);
        } catch (Throwable) {
            return OperationalHealthCheck::degraded('scheduler_unavailable');
        }
    }

    private function queue(): OperationalHealthCheck
    {
        try {
            $driver = (string) config('queue.default', 'sync');
            $workerMode = (string) config('operations.queue.worker_mode', 'none');

            if ($driver === 'sync' && $workerMode === 'none') {
                return OperationalHealthCheck::notApplicable('synchronous_queue');
            }

            if ($driver !== 'database' || $workerMode !== 'oneshot') {
                return OperationalHealthCheck::degraded('queue_configuration_invalid');
            }

            $connection = config('queue.connections.database');
            $table = is_array($connection) ? $connection['table'] ?? null : null;
            $threshold = $this->settings->values()['queue_backlog_threshold'];
            if (! is_string($table) || $table === '' || $threshold < 1 || ! Schema::hasTable($table)) {
                return OperationalHealthCheck::degraded('queue_unavailable');
            }

            if (DB::table($table)->count() > $threshold) {
                return OperationalHealthCheck::degraded('queue_backlog_high');
            }

            return OperationalHealthCheck::ok(['mode' => 'database']);
        } catch (Throwable) {
            return OperationalHealthCheck::degraded('queue_unavailable');
        }
    }

    private function scheduleContains(Schedule $schedule, string $command): bool
    {
        foreach ($schedule->events() as $event) {
            if (str_contains((string) $event->command, $command)) {
                return true;
            }
        }

        return false;
    }
}
