<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Payouts\Services\PayoutService;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class PayoutResolveRejected extends Command
{
    protected $signature = 'payouts:resolve-rejected {request : Nomor pengajuan SIM} {--confirm-not-created : Operator sudah memastikan pengajuan tidak tercatat pada Xendit Test Mode}';

    protected $description = 'Tutup penolakan HTTP 400 lama pada pengiriman pertama tanpa mengirim payout atau mengubah riwayat.';

    public function handle(PayoutService $service): int
    {
        try {
            $row = $service->resolveRejectedSubmission((string) $this->argument('request'), (bool) $this->option('confirm-not-created'));
        } catch (ValidationException $error) {
            foreach ($error->errors() as $messages) {
                foreach ($messages as $message) {
                    $this->error($message);
                }
            }

            return self::FAILURE;
        }
        $this->line($row->request_number.': '.$row->label().'. Saldo yang disisihkan tersedia kembali.');
        $this->line('Tidak ada payout baru, perubahan tujuan, atau mutasi keluar. Riwayat pengajuan tetap disimpan.');

        return self::SUCCESS;
    }
}
