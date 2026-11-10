<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutWorker;
use Illuminate\Console\Command;

final class PayoutRecover extends Command
{
    protected $signature = 'payouts:recover {id} {--reference= : Referensi pengajuan yang sudah diperiksa operator}';

    protected $description = 'Pulihkan pengiriman tidak pasti dengan isi dan kunci provider yang sama (sandbox saja).';

    public function handle(PayoutWorker $worker): int
    {
        app(PayoutAccess::class)->requireConfigured();
        $row = SandboxPayout::query()->find($this->argument('id'));
        if ($row === null || $row->reference_id !== $this->option('reference') || $row->status !== 'uncertain' || $row->provider_id !== null || $row->lease !== null || $row->updated_at->greaterThan(now()->subMinute())) {
            $this->error('Pemulihan ditolak. Periksa referensi, status tidak pasti tanpa ID provider, dan tunggu satu menit setelah upaya terakhir.');

            return self::FAILURE;
        }
        $ok = $worker->process($row->id, recoverSubmission: true);
        $this->line('Hasil: '.$row->fresh()->label().'. Tidak ada kunci atau pengajuan baru yang dibuat.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }
}
