<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Shared\InvalidValue;
use App\Domain\Telegram\Models\TelegramAction;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ProcessTelegramUpdate
{
    public function chat(string $telegramId, int $limit = 10, bool $allowDownloads = true): void
    {
        foreach (TelegramUpdate::query()->where('telegram_id', $telegramId)->where('status', 'pending')->orderBy('id')->limit(max(1, min(25, $limit)))->pluck('id') as $id) {
            if (! $allowDownloads && isset(TelegramUpdate::findOrFail($id)->payload['photo'])) {
                break;
            }
            $this->handle((int) $id);
        }
    }

    public function handle(int $id): void
    {
        $update = TelegramUpdate::query()->findOrFail($id);
        if ($update->status !== 'pending') {
            return;
        }
        try {
            app(TelegramFiles::class)->prepareIncoming($update);
            DB::transaction(function () use ($id): void {
                $update = TelegramUpdate::query()->lockForUpdate()->findOrFail($id);
                if ($update->status !== 'pending') {
                    return;
                }
                $link = TelegramLink::query()->where('telegram_id', $update->telegram_id)->first();
                if ($link !== null) {
                    User::query()->whereKey($link->user_id)->lockForUpdate()->first();
                    $link = TelegramLink::query()->whereKey($link->id)->lockForUpdate()->first();
                }
                TelegramConversation::query()->firstOrCreate(['telegram_id' => $update->telegram_id], ['link_id' => $link?->id, 'state' => 'menu', 'data' => [], 'expires_at' => now()->addMinutes(30)]);
                $conversation = TelegramConversation::query()->whereKey($update->telegram_id)->lockForUpdate()->firstOrFail();
                if ($conversation->getAttribute('last_update_id') !== null && (int) $conversation->getAttribute('last_update_id') >= $id) {
                    $update->forceFill(['status' => 'processed', 'payload' => [], 'processed_at' => now()])->save();

                    return;
                }
                if ($conversation->link_id !== $link?->id || $conversation->expires_at->isPast()) {
                    $conversation->forceFill(['link_id' => $link?->id, 'state' => 'menu', 'data' => []])->save();
                }
                $payload = $update->payload;
                if (isset($payload['link_hash'])) {
                    app(TelegramLinkService::class)->claim((string) $payload['link_hash'], $update->telegram_id, (string) ($payload['name'] ?? 'Telegram'));
                    app(TelegramUi::class)->reply($update->telegram_id, null, 'link:'.$id, 'Telegram sudah dikenali. Kembali ke halaman Profil → Telegram di website, periksa akun Telegram yang tampil, lalu konfirmasi penautan.');
                } else {
                    if ($link !== null) {
                        app(TelegramAccess::class)->actor($link);
                        if ($link->blocked_at !== null) {
                            $link->forceFill(['blocked_at' => null])->save();
                        }
                    }
                    $context = new BotContext($id, $update->telegram_id, $link, $conversation);
                    $command = '';
                    $parameters = [];
                    if (isset($payload['callback'])) {
                        app(TelegramUi::class)->queue($update->telegram_id, null, 'ack:'.$id, ['kind' => 'callback', 'callback_query_id' => $payload['callback_id']]);
                        if (preg_match('/^a:([a-f0-9]{32})$/', (string) $payload['callback'], $match) !== 1) {
                            throw ValidationException::withMessages(['action' => 'Tombol tidak berlaku. Ketik /menu.']);
                        }
                        $action = TelegramAction::query()->whereKey(hash('sha256', $match[1]))->where('telegram_id', $update->telegram_id)->lockForUpdate()->first();
                        if ($action === null || $action->expires_at->isPast() || $action->link_id !== $link?->id || $action->generation !== $link?->generation || ($action->consumed_by !== null && $action->consumed_by !== $id)) {
                            throw ValidationException::withMessages(['action' => 'Tombol sudah digunakan atau sesi berubah. Ketik /menu dan periksa status sebelum mengulang tindakan.']);
                        }
                        $command = $action->command;
                        $parameters = $action->parameters;
                        if (isset($parameters['__context']) && ! hash_equals((string) $parameters['__context'], BotContext::contextHash($conversation))) {
                            throw ValidationException::withMessages(['action' => 'Percakapan sudah berubah. Gunakan tombol pada pesan terbaru atau ketik /menu.']);
                        }
                        $action->forceFill(['consumed_by' => $id])->save();
                    }
                    app(TelegramBot::class)->handle($context, $command, $parameters, $payload);
                }
                // Only a fully processed interaction counts as valid activity for the staff idle window.
                $link?->forceFill(['last_activity_at' => now()])->save();
                $conversation->forceFill(['last_update_id' => $id])->save();
                $update->forceFill(['status' => 'processed', 'payload' => [], 'processed_at' => now(), 'error_code' => null])->save();
            }, 5);
        } catch (ValidationException|AuthorizationException|ModelNotFoundException|InvalidValue|HttpExceptionInterface $exception) {
            $text = $exception instanceof ValidationException ? implode("\n", array_map(static fn (array $messages): string => implode(' ', $messages), $exception->errors())) : 'Akses atau status sudah berubah. Ketik /menu untuk memeriksa kembali. Jangan mengulang serah fisik.';
            $this->fail($update, $text, true);
        } catch (TelegramApiFailure $exception) {
            $update->refresh()->forceFill(['error_code' => $exception->errorCode])->save();
            $this->fail($update, 'Foto belum dapat digunakan. Periksa ukuran/formatnya, lalu kirim ulang atau lewati foto. Data transaksi belum dicatat dari foto ini.', true);
        } catch (Throwable $exception) {
            // Keep evidence without serializing an HTTP exception, user text, or credential.
            $update->refresh()->forceFill(['attempts' => $update->attempts + 1, 'status' => $update->attempts >= 2 ? 'failed' : 'pending', 'error_code' => mb_substr(class_basename($exception), 0, 80)])->save();
            $this->fail($update, 'Permintaan belum dapat ditampilkan. Periksa status melalui /menu sebelum mencoba lagi. Jangan mengulang penyerahan uang atau paket.', false);
        }
    }

    private function fail(TelegramUpdate $update, string $text, bool $processed): void
    {
        // Error replies carry no account data, even when the link was just revoked.
        app(TelegramUi::class)->reply($update->telegram_id, null, 'error:'.$update->id, $text);
        if ($processed) {
            $update->refresh()->forceFill(['status' => 'processed', 'payload' => [], 'processed_at' => now()])->save();
        }
    }
}
