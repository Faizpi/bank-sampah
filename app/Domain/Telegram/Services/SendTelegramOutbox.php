<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class SendTelegramOutbox
{
    public function send(int $limit = 25, ?string $telegramId = null, bool $allowFiles = true, int $budgetSeconds = 20): int
    {
        if (! TelegramAccess::configured()) {
            return 0;
        }
        // A crashed sender may already have delivered. Do not blindly send that lease twice.
        TelegramOutbox::query()->where('status', 'leased')->where('leased_at', '<', now()->subMinutes(2))->update(['status' => 'uncertain', 'error_code' => 'telegram.stale_lease']);
        $sent = 0;
        $deadline = microtime(true) + max(1, $budgetSeconds);
        for ($i = 0; $i < max(0, min(100, $limit)); $i++) {
            if (microtime(true) >= $deadline) {
                break;
            }
            $message = DB::transaction(function () use ($telegramId, $allowFiles): ?TelegramOutbox {
                $row = TelegramOutbox::query()->where('status', 'pending')->where('available_at', '<=', now())
                    ->when(! $allowFiles, fn ($q) => $q->whereIn('kind', ['text', 'callback']))
                    ->when($telegramId !== null, fn ($q) => $q->where('telegram_id', $telegramId))->orderBy('sequence')->lockForUpdate()->first();
                if ($row !== null) {
                    if (TelegramOutbox::query()->where('telegram_id', $row->telegram_id)->where('status', 'leased')->lockForUpdate()->first() !== null) {
                        return null;
                    }
                    $row->forceFill(['status' => 'leased', 'lease' => (string) Str::uuid(), 'leased_at' => now(), 'attempts' => $row->attempts + 1])->save();
                }

                return $row;
            });
            if ($message === null) {
                break;
            }
            $link = null;
            $actor = null;
            try {
                if ($message->private) {
                    $link = TelegramLink::query()->whereKey($message->link_id)->where('telegram_id', $message->telegram_id)->first();
                    if ($link === null || $link->generation !== $message->getAttribute('generation') || $link->blocked_at !== null || ($message->category !== 'reply' && ! (bool) $link->getAttribute($message->category))) {
                        $message->forceFill(['status' => 'cancelled'])->save();

                        continue;
                    }
                    $actor = app(TelegramAccess::class)->actor($link);
                }
                $payload = $message->payload;
                foreach ($payload['guards'] ?? [] as $guard) {
                    if ($actor === null || ! app(TelegramRecordAccess::class)->allows($actor, $guard)) {
                        $message->forceFill(['status' => 'cancelled'])->save();

                        continue 2;
                    }
                }
                if ($payload['kind'] === 'announcements') {
                    $rows = app(AnnouncementService::class)->publicQuery()->whereIn('id', $payload['ids'])->get();
                    if ($rows->isEmpty()) {
                        $message->forceFill(['status' => 'cancelled'])->save();

                        continue;
                    }
                    $payload = ['kind' => 'text', 'text' => mb_substr($rows->map(fn ($row): string => $row->title."\n".strip_tags($row->body))->implode("\n\n"), 0, 4000)];
                }
                $parameters = ['chat_id' => $message->telegram_id];
                if ($payload['kind'] === 'text') {
                    $parameters += ['text' => $payload['text'], 'link_preview_options' => ['is_disabled' => true]];
                    if (isset($payload['reply_markup'])) {
                        $parameters['reply_markup'] = $payload['reply_markup'];
                    }
                    $result = app(TelegramApi::class)->call('sendMessage', $parameters);
                } elseif ($payload['kind'] === 'callback') {
                    $result = app(TelegramApi::class)->call('answerCallbackQuery', ['callback_query_id' => $payload['callback_query_id']]);
                } else {
                    $file = app(TelegramFiles::class)->outgoing($message, $actor);
                    if ($message->private) {
                        $current = TelegramLink::query()->whereKey($message->link_id)->where('telegram_id', $message->telegram_id)->firstOrFail();
                        if ($current->generation !== $message->getAttribute('generation')) {
                            $message->forceFill(['status' => 'cancelled'])->save();

                            continue;
                        }
                        app(TelegramAccess::class)->actor($current);
                    }
                    $parameters['caption'] = mb_substr((string) ($payload['caption'] ?? ''), 0, 900);
                    $result = app(TelegramApi::class)->call($file['method'], $parameters, $file['bytes'], $file['filename'], $file['method'] === 'sendPhoto' ? 'photo' : 'document');
                }
                $message->forceFill(['status' => 'sent', 'sent_at' => now(), 'message_id' => isset($result['message_id']) ? (string) $result['message_id'] : null, 'error_code' => null])->save();
                $sent++;
            } catch (TelegramApiFailure $failure) {
                $retry = $failure->retryAfter > 0 && $message->attempts < 5;
                $message->forceFill(['status' => $failure->uncertain ? 'uncertain' : ($retry ? 'pending' : 'failed'), 'available_at' => now()->addSeconds(max(1, $failure->retryAfter)), 'error_code' => $failure->errorCode])->save();
                if ($failure->blocked && $link !== null) {
                    $link->forceFill(['blocked_at' => now()])->save();
                }
            } catch (Throwable $failure) {
                $message->forceFill(['status' => 'failed', 'error_code' => mb_substr(class_basename($failure), 0, 80)])->save();
                if (! in_array($message->payload['kind'], ['text', 'callback', 'announcements'], true)) {
                    app(TelegramUi::class)->reply($message->telegram_id, $link, 'file-error:'.$message->id, 'Berkas belum tersedia atau hak akses berubah. Periksa kembali akun/periode melalui /menu. Data transaksi tidak berubah.');
                }
            }
        }

        return $sent;
    }
}
