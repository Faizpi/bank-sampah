<?php

declare(strict_types=1);

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Domain\Telegram\Services\TelegramUi;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Pages\TelegramConnections;
use App\Livewire\Profile\TelegramSettings;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
});

it('also enforces both one to one uniqueness constraints at database level', function (): void {
    $first = Bot::link($this->citizen, '10001');
    $other = User::where('username', 'bendahara')->firstOrFail();
    expect(fn () => TelegramLink::query()->create(['id' => (string) str()->uuid(), 'user_id' => $other->id, 'telegram_id' => '10001', 'chat_id' => '10001', 'account_fingerprint' => str_repeat('f', 64), 'generation' => (string) str()->uuid(), 'authorized_until' => now()->addHour()]))->toThrow(UniqueConstraintViolationException::class);
    expect(fn () => TelegramLink::query()->create(['id' => (string) str()->uuid(), 'user_id' => $this->citizen->id, 'telegram_id' => '10002', 'chat_id' => '10002', 'account_fingerprint' => str_repeat('f', 64), 'generation' => (string) str()->uuid(), 'authorized_until' => now()->addHour()]))->toThrow(UniqueConstraintViolationException::class);
    expect(TelegramLink::count())->toBe(1)->and($first->fresh()->user_id)->toBe($this->citizen->id);
});

it('does not trust the menu for role authorization', function (): void {
    $link = Bot::link($this->officer, '10002');
    Bot::text('10002', '/menu');
    app(TelegramUi::class)->reply('10002', $link, 'forged-menu-test', 'Menu sengaja salah untuk uji server', [['label' => 'Coba pembayaran', 'command' => 'service.queue', 'parameters' => ['kind' => 'cash']]]);
    Bot::click('10002', 'Coba pembayaran');
    expect(Bot::lastText('10002'))->toContain('Akses atau status sudah berubah');
    expect(LedgerEntry::count())->toBe(0);
});

it('rejects expired mutation authorization while still allowing a read only balance', function (): void {
    $link = Bot::link($this->citizen, '10001');
    $link->forceFill(['authorized_until' => now()->subMinute()])->save();
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    expect(Bot::lastText('10001'))->toContain('Otorisasi tindakan berakhir');
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Saldo');
    expect(Bot::lastText('10001'))->toContain('Saldo tersedia: Rp 0');
    expect(PickupRequest::count())->toBe(0);
});

it('never re finalizes a draft that the web has already completed', function (): void {
    Bot::link($this->officer, '10002');
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Catat setoran');
    $profile = $this->citizen->customerProfile;
    Bot::text('10002', $profile->customer_number);
    Bot::click('10002', $this->citizen->name.' · '.substr($profile->customer_number, 0, 4).'****'.substr($profile->customer_number, -2));
    Bot::click('10002', 'Aqua Botol');
    Bot::click('10002', 'Bersih dan Kering');
    Bot::text('10002', '1');
    $draft = Deposit::firstOrFail();
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    app(DepositService::class)->finalize($this->officer, $draft, (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '2']]);
    Bot::click('10002', 'Data benar — catat setoran');
    expect(LedgerEntry::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(3000);
    expect(Bot::lastText('10002'))->toContain('3.000');
});

it('locks the web challenge identity and confirms only the current users pending candidate', function (): void {
    $this->citizen->forceFill(['password' => 'TelegramOnly#2026'])->save();
    $this->actingAs($this->citizen);
    $screen = Livewire::test(TelegramSettings::class)->set('password', 'TelegramOnly#2026')->call('begin')->assertHasNoErrors()->assertSet('password', '');
    $challenge = TelegramChallenge::firstOrFail();
    app(TelegramLinkService::class)->claim($challenge->token_hash, '10001', 'Telegram uji');
    $screen->call('approve')->assertHasNoErrors()->assertSee('Telegram sudah tertaut');
    expect(TelegramLink::where('user_id', $this->citizen->id)->value('telegram_id'))->toBe('10001');
    expect(fn () => $screen->set('challengeId', (string) str()->uuid()))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps the webhook outside cookie sessions without excluding other web routes from CSRF', function (): void {
    $webhook = app('router')->getRoutes()->getByName('telegram.webhook');
    expect($webhook->gatherMiddleware())->not->toContain('web');
    $settings = app('router')->getRoutes()->getByName('telegram.settings');
    expect($settings->gatherMiddleware())->toContain('web', 'auth', 'session.fresh:30');
    config()->set('telegram.enabled', false);
    $this->postJson('/telegram/webhook', ['update_id' => 1])->assertNotFound();
});

it('ends staff transaction authorization after idle minutes while citizens keep theirs', function (): void {
    $staffLink = Bot::link($this->officer, '10002');
    $citizenLink = Bot::link($this->citizen, '10001');
    $this->travel(61)->minutes();
    expect(app(TelegramAccess::class)->actor($staffLink->fresh())->id)->toBe($this->officer->id);
    expect(fn () => app(TelegramAccess::class)->actor($staffLink->fresh(), true))->toThrow(
        fn (ValidationException $exception): bool => str_contains((string) $exception->errors()['authorization'][0], 'tidak ada aktivitas'),
    );
    expect(app(TelegramAccess::class)->actor($citizenLink->fresh(), true)->id)->toBe($this->citizen->id);
    Bot::text('10002', '/menu');
    expect(app(TelegramAccess::class)->actor($staffLink->fresh(), true)->id)->toBe($this->officer->id)
        ->and($staffLink->fresh()->last_activity_at?->format('Ymd Hi'))->toBe(now()->format('Ymd Hi'));
});

it('lets only an authorized Admin revoke another users link with a password and audit reason', function (): void {
    Bot::link($this->citizen, '10001');
    $this->admin->forceFill(['password' => 'AdminTelegram#2026'])->save();
    $this->actingAs($this->officer);
    expect(TelegramConnections::canAccess())->toBeFalse();
    $this->actingAs($this->admin);
    expect(TelegramConnections::canAccess())->toBeTrue();
    app(TelegramLinkService::class)->revoke($this->admin, $this->citizen, 'AdminTelegram#2026', 'Pengguna melaporkan kehilangan akses Telegram.');
    expect(TelegramLink::count())->toBe(0);
    $this->assertDatabaseHas('audit_logs', ['action' => 'telegram.link.revoked', 'actor_id' => $this->admin->id]);
    Http::assertNothingSent();
});

it('records explicit announcement consent and old activate buttons cannot silently unsubscribe', function (): void {
    $link = Bot::link($this->citizen, '10001');
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Notifikasi');
    $old = Bot::button('10001', 'Aktifkan Langganan pengumuman');
    Bot::click('10001', 'Aktifkan Langganan pengumuman');
    expect($link->fresh()->announcements)->toBeTrue();
    Bot::update('10001', ['callback' => $old, 'callback_id' => 'old-consent']);
    expect($link->fresh()->announcements)->toBeTrue();
    $this->assertDatabaseHas('audit_logs', ['action' => 'telegram.preferences.updated', 'actor_id' => $this->citizen->id]);
});
