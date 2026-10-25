<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramNotifications;
use Illuminate\Console\Command;

final class TelegramNotify extends Command
{
    protected $signature = 'telegram:notify';

    protected $description = 'Siapkan pengingat, ringkasan, pengumuman, dan pemulihan notifikasi Telegram.';

    public function handle(TelegramNotifications $notifications): int
    {
        if (! TelegramAccess::configured()) {
            $this->error('Telegram belum dikonfigurasi.');

            return self::FAILURE;
        }
        $notifications->produce();
        $this->info('Antrean notifikasi diperiksa.');

        return self::SUCCESS;
    }
}
