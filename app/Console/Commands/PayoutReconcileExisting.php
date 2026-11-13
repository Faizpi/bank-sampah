<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutSettlement;
use App\Domain\Payouts\Services\XenditPayoutApi;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PayoutReconcileExisting extends Command
{
    protected $signature = 'payouts:reconcile-existing {request : Nomor SIM} {provider : Payout ID dari Xendit}';

    protected $description = 'Cocokkan satu pengajuan tidak pasti dengan payout yang sudah ada di Xendit Test Mode.';

    public function handle(XenditPayoutApi $api, PayoutSettlement $settlement): int
    {
        app(PayoutAccess::class)->requireConfigured();
        $number = (string) $this->argument('request');
        $provider = (string) $this->argument('provider');
        if (preg_match('/^SIM-[0-9]{14}-[A-Z0-9]{8}$/D', $number) !== 1 || preg_match('/^po-[A-Za-z0-9-]{1,90}$/D', $provider) !== 1) {
            $this->error('Nomor SIM atau Payout ID tidak valid.');

            return self::FAILURE;
        }
        $row = SandboxPayout::query()->where('request_number', $number)->first();
        if ($row === null || $row->status !== 'uncertain' || $row->provider_id !== null || $row->lease !== null) {
            $this->error('Pengajuan tidak tersedia untuk pencocokan. Periksa status dan ID yang sudah tersimpan.');

            return self::FAILURE;
        }
        try {
            // GET only; never send the creation request or account details again.
            $data = $api->find($provider);
            if (($data['payout_id'] ?? null) !== $provider) {
                throw new \RuntimeException('Payout ID mismatch.');
            }
            $updated = DB::transaction(function () use ($row, $settlement, $data): SandboxPayout {
                $locked = SandboxPayout::query()->lockForUpdate()->findOrFail($row->id);
                if ($locked->status !== 'uncertain' || $locked->provider_id !== null || $locked->lease !== null) {
                    throw new \RuntimeException('Payout changed during lookup.');
                }

                return $settlement->apply($locked->id, $data, providerLookup: true);
            }, 5);
        } catch (Throwable) {
            $this->error('Pencocokan belum dapat dipastikan. Tidak ada saldo yang diubah.');

            return self::FAILURE;
        }
        $this->line($updated->request_number.': '.$updated->label().'. Tidak ada pengajuan baru.');
        $this->line('Saldo mengikuti hasil Xendit setelah identitas dan nominal berhasil dicocokkan.');

        return self::SUCCESS;
    }
}
