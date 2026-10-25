<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\ProcessTelegramUpdate;
use App\Domain\Telegram\Services\SendTelegramOutbox;
use App\Domain\Telegram\Services\TelegramAccess;
use Illuminate\Console\Command;

final class TelegramWork extends Command
{
    protected $signature = 'telegram:work {--limit=25}';

    protected $description = 'Proses pembaruan dan antrean kirim Telegram dalam batch terbatas.';

    public function handle(): int
    {
        if (! TelegramAccess::configured()) {
            $this->error('Telegram belum diaktifkan/dikonfigurasi.');

            return self::FAILURE;
        }
        $start = microtime(true);
        $limit = max(1, min(100, (int) $this->option('limit')));
        $count = 0;
        foreach (TelegramUpdate::query()->where('status', 'pending')->orderBy('id')->limit($limit)->pluck('id') as $id) {
            if (microtime(true) - $start > 30) {
                break;
            }
            app(ProcessTelegramUpdate::class)->handle((int) $id);
            $count++;
        }
        $sent = app(SendTelegramOutbox::class)->send($limit, budgetSeconds: max(1, (int) (45 - (microtime(true) - $start))));
        $this->info('Pembaruan diproses: '.$count.'. Pesan dikirim: '.$sent.'.');

        return self::SUCCESS;
    }
}
