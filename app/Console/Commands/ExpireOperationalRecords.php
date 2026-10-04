<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Operations\Services\ScheduledOperationsService;
use App\Domain\Operations\Services\SchedulerHeartbeat;
use Illuminate\Console\Command;

final class ExpireOperationalRecords extends Command
{
    protected $signature = 'operations:expire';

    protected $description = 'Expires eligible operational records in bounded batches.';

    public function handle(ScheduledOperationsService $operations, SchedulerHeartbeat $heartbeat): int
    {
        $result = $operations->expireEligible();
        $heartbeat->record(SchedulerHeartbeat::EXPIRE_KEY);

        $this->components->info(sprintf(
            'Expired withdrawals: %d; groceries: %d; exports: %d.',
            $result['withdrawals'],
            $result['groceries'],
            $result['exports'],
        ));

        return self::SUCCESS;
    }
}
