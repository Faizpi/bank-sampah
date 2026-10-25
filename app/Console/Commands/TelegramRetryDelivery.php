<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Models\TelegramOutbox;
use Illuminate\Console\Command;

final class TelegramRetryDelivery extends Command
{
    protected $signature = 'telegram:retry-delivery {id} {--acknowledge-duplicate}';

    protected $description = 'Antrekan ulang satu pengiriman yang diperiksa operator; tidak menjalankan ulang transaksi.';

    public function handle(): int
    {
        $message = TelegramOutbox::query()->find((string) $this->argument('id'));
        if ($message === null || ! in_array($message->status, ['failed', 'uncertain'], true)) {
            $this->error('Pilih ID pengiriman gagal/tidak pasti yang benar.');

            return self::FAILURE;
        }
        if ($message->status === 'uncertain' && ! $this->option('acknowledge-duplicate')) {
            $this->error('Pesan mungkin sudah terkirim. Periksa dahulu; --acknowledge-duplicate mengizinkan kemungkinan pesan ganda, bukan transaksi ganda.');

            return self::FAILURE;
        }
        $message->forceFill(['status' => 'pending', 'available_at' => now(), 'lease' => null, 'leased_at' => null, 'attempts' => 0])->save();
        $this->info('Pesan diantrekan ulang. Tautan dan izin diperiksa lagi saat dikirim.');

        return self::SUCCESS;
    }
}
