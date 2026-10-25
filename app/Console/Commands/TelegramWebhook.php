<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramApi;
use App\Domain\Telegram\Services\TelegramApiFailure;
use Illuminate\Console\Command;

final class TelegramWebhook extends Command
{
    protected $signature = 'telegram:webhook {--remove : Hapus webhook tanpa membuang update tertunda}';

    protected $description = 'Daftarkan webhook HTTPS Telegram menggunakan konfigurasi rahasia server.';

    public function handle(TelegramApi $api): int
    {
        if (! TelegramAccess::configured()) {
            $this->error('Lengkapi konfigurasi Telegram di .env server.');

            return self::FAILURE;
        }
        $url = route('telegram.webhook');
        $host = (string) parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || $host === 'localhost' || str_ends_with($host, '.test') || (filter_var($host, FILTER_VALIDATE_IP) && ! filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))) {
            $this->error('Webhook memerlukan APP_URL HTTPS yang dapat diakses Telegram.');

            return self::FAILURE;
        }
        try {
            if ($this->option('remove')) {
                $api->call('deleteWebhook', ['drop_pending_updates' => false]);
            } else {
                $bot = $api->call('getMe', []);
                if (strcasecmp((string) ($bot['username'] ?? ''), (string) config('telegram.bot_username')) !== 0) {
                    $this->error('Username bot tidak sesuai token.');

                    return self::FAILURE;
                }
                $api->call('setWebhook', ['url' => $url, 'secret_token' => config('telegram.webhook_secret'), 'allowed_updates' => ['message', 'callback_query'], 'drop_pending_updates' => false]);
            }
        } catch (TelegramApiFailure $error) {
            $this->error($error->errorCode);

            return self::FAILURE;
        }
        $this->info('Konfigurasi webhook diperbarui. Token tidak ditampilkan.');

        return self::SUCCESS;
    }
}
