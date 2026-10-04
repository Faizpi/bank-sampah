<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Services\ScheduledOperationsService;
use App\Domain\Operations\Services\SchedulerHeartbeat;
use Illuminate\Console\Command;

final class PurgeOperationalRows extends Command
{
    protected $signature = 'operations:purge';

    protected $description = 'Purges expired idempotency keys and stale notification failures in bounded batches.';

    public function handle(ScheduledOperationsService $operations, SchedulerHeartbeat $heartbeat): int
    {
        $result = $operations->purgeExpiredOperationalRows();
        $heartbeat->record(SchedulerHeartbeat::PURGE_KEY);

        $this->components->info(sprintf(
            'Purged idempotency keys: %d; notification failures: %d.',
            $result['idempotency_keys'],
            $result['notification_failures'],
        ));

        return self::SUCCESS;
    }
}
