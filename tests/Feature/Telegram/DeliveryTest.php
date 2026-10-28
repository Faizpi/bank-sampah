<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Services\SendTelegramOutbox;
use App\Domain\Telegram\Services\TelegramApi;
use App\Domain\Telegram\Services\TelegramApiFailure;
use App\Domain\Telegram\Services\TelegramFiles;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Domain\Telegram\Services\TelegramUi;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $this->actor = User::where('username', 'warga')->firstOrFail();
    $this->link = Bot::link($this->actor, '10001');
    TelegramOutbox::query()->delete();
});

it('encrypts message content and deduplicates logical delivery', function (): void {
    $ui = app(TelegramUi::class);
    $first = $ui->reply('10001', $this->link, 'same-key', 'Saldo pribadi 12345');
    $second = $ui->reply('10001', $this->link, 'same-key', 'Saldo pribadi 12345');
    expect($first->id)->toBe($second->id)->and($first->getRawOriginal('payload'))->not->toContain('Saldo pribadi');
    expect(app(SendTelegramOutbox::class)->send())->toBe(1);
    expect(app(SendTelegramOutbox::class)->send())->toBe(0);
    Http::assertSentCount(1);
});

it('waits for a definite rate limit without modifying transactions', function (): void {
    app(TelegramUi::class)->reply('10001', $this->link, 'limited', 'Pesan uji');
    Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 429, 'parameters' => ['retry_after' => 30]], 429)]);
    expect(app(SendTelegramOutbox::class)->send())->toBe(0);
    $message = TelegramOutbox::firstOrFail();
    expect($message->status)->toBe('pending')->and($message->attempts)->toBe(1)->and($message->available_at->isFuture())->toBeTrue();
    expect(app(SendTelegramOutbox::class)->send())->toBe(0);
    Http::assertSentCount(1);
    $this->travel(31)->seconds();
    Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]])]);
    expect(app(SendTelegramOutbox::class)->send())->toBe(1);
    expect(LedgerEntry::count())->toBe(0);
});

it('does not blindly retry ambiguous delivery and does not persist credential URLs', function (): void {
    app(TelegramUi::class)->reply('10001', $this->link, 'ambiguous', 'Pesan uji');
    $attempts = 0;
    Bot::fakeHttp(function () use (&$attempts) {
        $attempts++;
        throw new ConnectionException('https://api.telegram.org/bot123456:TEST/sendMessage');
    });
    expect(app(SendTelegramOutbox::class)->send())->toBe(0);
    $message = TelegramOutbox::firstOrFail();
    expect($message->status)->toBe('uncertain')->and($message->getAttribute('error_code'))->toBe('telegram.transport');
    expect(app(SendTelegramOutbox::class)->send())->toBe(0);
    expect($attempts)->toBe(1);
});

it('drops messages from a revoked connection or an old authorization generation', function (): void {
    $message = app(TelegramUi::class)->reply('10001', $this->link, 'old-generation', 'Data pribadi lama');
    Bot::link($this->actor, '10001');
    app(SendTelegramOutbox::class)->send();
    expect($message->fresh()->status)->toBe('cancelled');
    $new = app(TelegramUi::class)->reply('10001', $this->link->fresh(), 'revoked-generation', 'Data privat');
    app(TelegramLinkService::class)->revoke($this->actor, $this->actor, 'TelegramOnly#2026');
    $before = Http::recorded()->count();
    app(SendTelegramOutbox::class)->send();
    expect($new->fresh()->status)->toBe('cancelled')->and(Http::recorded()->count())->toBe($before);
});

it('sends permitted notifications after long inactivity but stops disabled accounts', function (): void {
    $this->link->forceFill(['transaction_notifications' => true])->save();
    $this->travel(400)->days();
    app(TelegramUi::class)->queue('10001', $this->link, 'permitted-notice', ['kind' => 'text', 'text' => 'Informasi transaksi'], 'transaction_notifications');
    expect(app(SendTelegramOutbox::class)->send())->toBe(1);
    $message = app(TelegramUi::class)->reply('10001', $this->link, 'disabled-notice', 'Privat');
    $this->actor->forceFill(['status' => UserStatus::Inactive])->save();
    expect(app(SendTelegramOutbox::class)->send())->toBe(0)->and($message->fresh()->status)->toBe('failed');
    Http::assertSentCount(1);
});

it('marks a blocked recipient and avoids repeatedly sending its private queue', function (): void {
    app(TelegramUi::class)->reply('10001', $this->link, 'blocked-first', 'Pesan satu');
    $next = app(TelegramUi::class)->reply('10001', $this->link, 'blocked-next', 'Pesan dua');
    Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 403], 403)]);
    app(SendTelegramOutbox::class)->send();
    expect($this->link->fresh()->blocked_at)->not->toBeNull()->and($next->fresh()->status)->toBe('cancelled');
    Http::assertSentCount(1);
});

it('produces a PNG card without rotating or exposing the QR token in the message queue', function (): void {
    $before = $this->actor->customerProfile->qr_token_hash;
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Kartu nasabah');
    $message = TelegramOutbox::where('kind', 'card')->firstOrFail();
    $file = app(TelegramFiles::class)->outgoing($message, $this->actor);
    expect(substr($file['bytes'], 0, 8))->toBe("\x89PNG\r\n\x1a\n");
    $info = getimagesizefromstring($file['bytes']);
    expect($info[0])->toBeGreaterThanOrEqual(200);
    expect($this->actor->customerProfile->fresh()->qr_token_hash)->toBe($before);
    expect($message->payload)->not->toHaveKey('token');
});

it('rejects file traversal and non images from the Telegram media boundary', function (): void {
    Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['file_path' => '../../private/.env']])]);
    expect(fn () => app(TelegramApi::class)->downloadPhoto('valid_file'))->toThrow(TelegramApiFailure::class);
    Http::assertSentCount(1);
    Bot::fakeHttp([
        'https://api.telegram.org/bot123456:TEST/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file.jpg']]),
        'https://api.telegram.org/file/bot123456:TEST/photos/file.jpg' => Http::response('not an image'),
    ]);
    expect(fn () => app(TelegramApi::class)->downloadPhoto('valid_file'))->toThrow(TelegramApiFailure::class);
});

it('keeps actual photo intake private and passes it through the same pickup media validation', function (): void {
    $photo = UploadedFile::fake()->image('test.png', 30, 30);
    Bot::fakeHttp([
        'https://api.telegram.org/bot123456:TEST/getFile' => Http::response(['ok' => true, 'result' => ['file_path' => 'photos/file.jpg', 'file_size' => $photo->getSize()]]),
        'https://api.telegram.org/file/bot123456:TEST/photos/file.jpg' => Http::response($photo->getContent()),
    ]);
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    Bot::click('10001', 'Hari ini');
    Bot::click('10001', 'Gunakan alamat profil');
    Bot::click('10001', 'Aqua Botol');
    Bot::click('10001', 'Selesai pilih jenis');
    Bot::click('10001', 'Tambahkan foto');
    Bot::update('10001', ['photo' => 'valid_file']);
    Bot::click('10001', 'Selesai');
    Bot::click('10001', 'Ya, kirim pengajuan');
    test()->assertSame(1, PickupRequest::count(), Bot::lastText('10001'));
    $pickup = PickupRequest::firstOrFail();
    expect($pickup->media()->count())->toBe(1);
    $media = $pickup->media()->firstOrFail();
    expect($media->disk)->toBe('media_private')->and($media->mime_type)->toBe('image/jpeg');
});
