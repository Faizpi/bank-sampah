<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Models\TelegramAction;
use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Models\TelegramUpdate;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class TelegramPrune extends Command
{
    protected $signature = 'telegram:prune';

    protected $description = 'Hapus hanya data sementara kanal Telegram yang melewati retensi.';

    public function handle(): int
    {
        if (! Schema::hasTable('telegram_links')) {
            $this->error('Migration Telegram belum terpasang.');

            return self::FAILURE;
        }
        $before = now()->subDays(max(1, (int) config('telegram.retention_days')));
        TelegramUpdate::query()->where('created_at', '<', $before)->delete();
        TelegramOutbox::query()->where('created_at', '<', $before)->delete();
        TelegramAction::query()->where('expires_at', '<', now()->subDay())->delete();
        TelegramChallenge::query()->where('expires_at', '<', now()->subDay())->delete();
        TelegramConversation::query()->where('expires_at', '<', now()->subDay())->delete();
        $disk = Storage::disk('media_private');
        foreach ($disk->allFiles('telegram/uploads') as $path) {
            if (str_starts_with($path, 'telegram/uploads/') && ! str_contains($path, '..') && $disk->lastModified($path) < now()->subDay()->getTimestamp()) {
                $disk->delete($path);
            }
        }
        $this->info('Data sementara Telegram yang kedaluwarsa dibersihkan; transaksi utama tidak diubah.');

        return self::SUCCESS;
    }
}
