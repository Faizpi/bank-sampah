<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class PayoutDiagnose extends Command
{
    protected $signature = 'payouts:diagnose';

    protected $description = 'Periksa kesiapan sandbox tanpa menampilkan rahasia atau mengirim payout.';

    public function handle(): int
    {
        $configured = PayoutAccess::configured();
        $tables = Schema::hasTable('sandbox_payouts') && Schema::hasTable('sandbox_payout_events');
        $this->line(($configured ? '[OK]' : '[FAIL]').' konfigurasi sandbox dan isolasi database');
        $this->line(($tables ? '[OK]' : '[FAIL]').' migrasi pencairan simulasi');
        foreach (app(PayoutChannels::class)->all() as $row) {
            $this->line(($row['ready'] ? '[OK]' : '[INFO]').' routing v3 '.$row['name']);
        }
        if ($tables) {
            $this->line('Permintaan berstatus tidak pasti: '.SandboxPayout::query()->where('status', 'uncertain')->count());
        }
        $this->line('Tidak ada uang riil yang dikirim. Kesiapan channel perlu dibuktikan melalui akun Test Mode.');

        return $configured && $tables ? self::SUCCESS : self::FAILURE;
    }
}
