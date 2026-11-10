<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutWorker;
use Illuminate\Console\Command;

final class PayoutWork extends Command
{
    protected $signature = 'payouts:work {--limit=10}';

    protected $description = 'Proses pencairan simulasi dan periksa ulang status Xendit Test Mode.';

    public function handle(PayoutWorker $worker): int
    {
        if (! PayoutAccess::configured()) {
            $this->error('Sandbox belum siap. Periksa database riset, Development API Key, dan token webhook.');

            return self::FAILURE;
        }
        $this->info('Permintaan simulasi diperiksa: '.$worker->run((int) $this->option('limit')).'.');

        return self::SUCCESS;
    }
}
