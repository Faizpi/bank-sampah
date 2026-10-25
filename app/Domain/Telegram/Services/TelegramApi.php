<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use Illuminate\Support\Facades\Http;
use Throwable;

final class TelegramApi
{
    /** @param array<string, mixed> $parameters
     * @return array<string, mixed>
     */
    public function call(string $method, array $parameters, ?string $bytes = null, ?string $filename = null, string $fileField = 'document'): array
    {
        if (! in_array($method, ['sendMessage', 'sendPhoto', 'sendDocument', 'answerCallbackQuery', 'getFile', 'getMe', 'setWebhook', 'getWebhookInfo', 'deleteWebhook'], true) || ! TelegramAccess::configured()) {
            throw new TelegramApiFailure('telegram.not_configured');
        }
        try {
            $request = Http::connectTimeout(3)->timeout((int) config('telegram.http_timeout'))->acceptJson();
            if ($bytes !== null) {
                foreach ($parameters as $key => $value) {
                    if (is_array($value)) {
                        $parameters[$key] = json_encode($value, JSON_THROW_ON_ERROR);
                    }
                }
                $request = $request->attach($fileField, $bytes, $filename ?? 'file');
            }
            $response = $request->post('https://api.telegram.org/bot'.config('telegram.bot_token').'/'.$method, $parameters);
        } catch (Throwable) {
            throw new TelegramApiFailure('telegram.transport', uncertain: ! in_array($method, ['getFile', 'getMe', 'getWebhookInfo'], true));
        }
        $data = $response->json();
        if (is_array($data) && ($data['ok'] ?? null) === true && $response->successful()) {
            return is_array($data['result'] ?? null) ? $data['result'] : ['ok' => true];
        }
        $code = (int) (is_array($data) ? ($data['error_code'] ?? $response->status()) : $response->status());
        $retry = $code === 429 ? max(1, min(86400, (int) data_get($data, 'parameters.retry_after', 30))) : 0;
        if ($code >= 500 && is_array($data) && ($data['ok'] ?? null) === false) {
            $retry = 60;
        }
        throw new TelegramApiFailure('telegram.http_'.$code, $retry, $code >= 500 && $retry === 0, $code === 403);
    }

    public function downloadPhoto(string $fileId): string
    {
        if (preg_match('/^[A-Za-z0-9_=-]{1,255}$/', $fileId) !== 1) {
            throw new TelegramApiFailure('telegram.invalid_file');
        }
        $file = $this->call('getFile', ['file_id' => $fileId]);
        $path = $file['file_path'] ?? null;
        $max = (int) config('telegram.max_upload_bytes');
        if (! is_string($path) || str_contains($path, '..') || preg_match('/^[A-Za-z0-9_-]+\/[A-Za-z0-9_\/.\-]+$/', $path) !== 1 || (int) ($file['file_size'] ?? 0) > $max) {
            throw new TelegramApiFailure('telegram.invalid_file');
        }
        try {
            $response = Http::connectTimeout(3)->timeout(10)->withOptions(['stream' => true, 'allow_redirects' => false])->get('https://api.telegram.org/file/bot'.config('telegram.bot_token').'/'.$path);
            if (! $response->successful()) {
                throw new TelegramApiFailure('telegram.file_unavailable');
            }
            $stream = $response->toPsrResponse()->getBody();
            $bytes = '';
            while (! $stream->eof() && strlen($bytes) <= $max) {
                $bytes .= $stream->read(min(65536, $max + 1 - strlen($bytes)));
            }
            $stream->close();
        } catch (Throwable) {
            throw new TelegramApiFailure('telegram.file_unavailable', 30);
        }
        $info = @getimagesizefromstring($bytes);
        if (strlen($bytes) > $max || $info === false || $info[0] * $info[1] > 24_000_000 || ! in_array($info['mime'], ['image/jpeg', 'image/png'], true)) {
            throw new TelegramApiFailure('telegram.invalid_image');
        }

        return $bytes;
    }
}
