<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Models\User;
use chillerlan\QRCode\Output\QROutputInterface;
use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

final class TelegramFiles
{
    public function prepareIncoming(TelegramUpdate $update): void
    {
        if (! isset($update->payload['photo']) || isset($update->payload['upload'])) {
            return;
        }
        $link = TelegramLink::query()->where('telegram_id', $update->telegram_id)->first();
        $conversation = TelegramConversation::query()->find($update->telegram_id);
        if ($link === null || $conversation === null || $conversation->link_id !== $link->id || $conversation->expires_at->isPast() || ! str_ends_with($conversation->state, '.photo')) {
            throw ValidationException::withMessages(['photo' => 'Pilih Tambahkan foto pada pengajuan atau tugas terlebih dahulu.']);
        }
        app(TelegramAccess::class)->actor($link, true);
        if ($conversation->state === 'citizen.pickup.photo' && count($conversation->data['photos'] ?? []) >= 2) {
            throw ValidationException::withMessages(['photo' => 'Maksimal dua foto. Pilih Selesai untuk melanjutkan.']);
        }
        $bytes = app(TelegramApi::class)->downloadPhoto((string) $update->payload['photo']);
        $mime = (string) (getimagesizefromstring($bytes)['mime'] ?? '');
        $path = 'telegram/uploads/'.$link->id.'/'.$update->id.'.image';
        if (! Storage::disk('media_private')->put($path, $bytes)) {
            throw new TelegramApiFailure('telegram.upload_storage');
        }
        $update->forceFill(['payload' => [...$update->payload, 'upload' => ['path' => $path, 'mime' => $mime, 'checksum' => hash('sha256', $bytes)]]])->save();
    }

    /** @param list<array<string, string>> $files
     * @return list<UploadedFile>
     */
    public function uploads(TelegramLink $link, array $files): array
    {
        $result = [];
        foreach (array_slice($files, 0, 2) as $file) {
            $path = $file['path'] ?? '';
            if (! str_starts_with($path, 'telegram/uploads/'.$link->id.'/') || str_contains($path, '..') || ! Storage::disk('media_private')->exists($path)) {
                throw ValidationException::withMessages(['photo' => 'Foto sudah tidak tersedia. Tambahkan kembali.']);
            }
            $bytes = Storage::disk('media_private')->get($path);
            if (! hash_equals($file['checksum'] ?? '', hash('sha256', $bytes))) {
                throw ValidationException::withMessages(['photo' => 'Foto berubah. Tambahkan kembali.']);
            }
            $name = $file['mime'] === 'image/png' ? 'telegram-photo.png' : 'telegram-photo.jpg';
            $result[] = new UploadedFile(Storage::disk('media_private')->path($path), $name, $file['mime'], null, true);
        }

        return $result;
    }

    /** @return array{bytes: string, filename: string, method: string} */
    public function outgoing(TelegramOutbox $message, ?User $actor): array
    {
        $payload = $message->payload;
        if ($payload['kind'] === 'card') {
            abort_unless($actor?->hasRole('warga'), 403);
            $profile = $actor->customerProfile;
            $raw = $profile?->qr_token_encrypted;
            if ($profile === null || ! is_string($raw) || ! is_string($profile->customer_number) || ! hash_equals((string) $profile->qr_token_hash, hash('sha256', $raw))) {
                throw ValidationException::withMessages(['card' => 'Kartu belum tersedia. Hubungi Admin untuk melengkapi kartu.']);
            }
            $token = QrToken::fromValue($raw);
            $options = new QROptions(['outputType' => QROutputInterface::GDIMAGE_PNG, 'outputBase64' => false, 'scale' => 8, 'addQuietzone' => true, 'quietzoneSize' => 4]);

            return ['bytes' => (string) (new QRCode($options))->render($token->value()), 'filename' => 'kartu-nasabah.png', 'method' => 'sendPhoto'];
        }
        if ($payload['kind'] === 'catalog-photo') {
            $type = app(PublicWasteCatalog::class)->visibleTypes()->with('publicPhoto')->findOrFail((int) $payload['type_id']);
            $photo = $type->publicPhoto;
            if ($photo === null || preg_match('/^[a-f0-9-]{36}\.jpg$/', $photo->path) !== 1 || ! Storage::disk('waste_photos')->exists($photo->path)) {
                throw new TelegramApiFailure('telegram.catalog_photo_unavailable');
            }

            return ['bytes' => Storage::disk('waste_photos')->get($photo->path), 'filename' => 'jenis-sampah.jpg', 'method' => 'sendPhoto'];
        }
        abort_unless($payload['kind'] === 'report' && $actor?->hasRole('bendahara'), 403);
        $exports = app(ReportExportService::class);
        if (isset($payload['export_id'])) {
            $export = ReportExport::query()->where('requester_id', $actor->id)->findOrFail($payload['export_id']);
        } else {
            $export = $exports->export($actor, 'withdrawals', ['start' => $payload['start'], 'end' => $payload['end']], 'xlsx');
            $message->forceFill(['payload' => [...$payload, 'export_id' => $export->id]])->save();
        }
        $media = $exports->download($actor, $export);

        return ['bytes' => Storage::disk($media->disk)->get($media->path), 'filename' => (string) $export->filename, 'method' => 'sendDocument'];
    }
}
