<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramApi;
use App\Domain\Telegram\Services\TelegramApiFailure;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;

final class TelegramDiagnose extends Command
{
    protected $signature = 'telegram:diagnose {--network : Periksa getMe/getWebhookInfo tanpa mengubah webhook}';

    protected $description = 'Pemeriksaan kesiapan Telegram tanpa menampilkan rahasia atau isi pesan.';

    public function handle(): int
    {
        $ready = TelegramAccess::configured();
        $this->line(($ready ? '[OK]' : '[FAIL]').' konfigurasi Telegram');
        $tables = true;
        foreach (['telegram_links', 'telegram_challenges', 'telegram_updates', 'telegram_conversations', 'telegram_actions', 'telegram_outbox'] as $name) {
            $tables = Schema::hasTable($name) && $tables;
        }
        $this->line(($tables ? '[OK]' : '[FAIL]').' migration kanal Telegram');
        $this->line((extension_loaded('gd') ? '[OK]' : '[FAIL]').' GD untuk QR/foto');
        $this->line((class_exists(\ZipArchive::class) ? '[OK]' : '[FAIL]').' ZIP untuk Excel');
        if ($tables) {
            $this->line('Update gagal: '.TelegramUpdate::query()->where('status', 'failed')->count());
            $this->line('Pengiriman tidak pasti: '.TelegramOutbox::query()->where('status', 'uncertain')->count());
        }
        if ($ready && $this->option('network')) {
            $expectedUrl = route('telegram.webhook');
            $this->line('Alamat webhook aplikasi: '.TelegramApiFailure::safeUrl($expectedUrl));
            try {
                $api = app(TelegramApi::class);
                $bot = $api->call('getMe', []);
                $info = $api->call('getWebhookInfo', []);
                $same = strcasecmp((string) ($bot['username'] ?? ''), (string) config('telegram.bot_username')) === 0;
                $this->line(($same ? '[OK]' : '[FAIL]').' identitas bot');
                $this->line('Update menunggu di Telegram: '.(int) ($info['pending_update_count'] ?? 0));
                $registeredUrl = is_string($info['url'] ?? null) ? $info['url'] : '';
                if ($registeredUrl === '') {
                    $this->line('[INFO] Webhook di Telegram belum terpasang.');
                } else {
                    $this->line('Webhook di Telegram: '.TelegramApiFailure::safeUrl($registeredUrl));
                    $this->line(($registeredUrl === $expectedUrl ? '[OK]' : '[WARN]').' kecocokan alamat webhook');
                }
                $lastError = TelegramApiFailure::safeDescription($info['last_error_message'] ?? '');
                if ($lastError !== '') {
                    $this->line('Kesalahan pengiriman terakhir: '.$lastError);
                }
                $ready = $same;
            } catch (TelegramApiFailure $error) {
                $this->error($error->errorCode);
                if ($error->description !== '') {
                    $this->line('Alasan Telegram: '.$error->description);
                }

                return self::FAILURE;
            }
        }

        return $ready && $tables && extension_loaded('gd') && class_exists(\ZipArchive::class) ? self::SUCCESS : self::FAILURE;
    }
}
