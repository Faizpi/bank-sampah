<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Telegram\Models\TelegramChallenge;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\ProcessTelegramUpdate;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config()->set(['telegram.enabled' => true, 'telegram.bot_username' => 'sindangheula_test_bot', 'telegram.bot_token' => '123456:TEST', 'telegram.webhook_secret' => str_repeat('s', 40), 'telegram.inline_processing' => false]);
    Http::preventStrayRequests();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]])]);
    $this->actor = User::factory()->create(['role' => 'warga', 'status' => UserStatus::Active, 'password' => 'TelegramOnly#2026']);
    $this->connect = function (User $actor, string $id): TelegramLink {
        $service = app(TelegramLinkService::class);
        $start = $service->begin($actor, 'TelegramOnly#2026');
        $service->claim(TelegramChallenge::findOrFail($start['id'])->token_hash, $id, 'Telegram uji');

        return $service->approve($actor, $start['id'], ['transaction_notifications' => true, 'reminders' => false, 'summaries' => false, 'announcements' => false, 'summary_frequency' => 'weekly']);
    };
});

it('requires both Telegram possession and explicit web confirmation', function (): void {
    $service = app(TelegramLinkService::class);
    $start = $service->begin($this->actor, 'TelegramOnly#2026');
    $raw = explode('link_', $start['url'])[1];
    $challenge = TelegramChallenge::findOrFail($start['id']);
    expect($challenge->token_hash)->toBe(hash('sha256', $raw))->not->toBe($raw);
    expect(fn () => $service->approve($this->actor, $challenge->id, []))->toThrow(ValidationException::class);
    $service->claim($challenge->token_hash, '10001', 'Akun Telegram');
    expect(TelegramLink::count())->toBe(0);
    $link = $service->approve($this->actor, $challenge->id, ['transaction_notifications' => true, 'reminders' => false, 'summaries' => false, 'announcements' => false, 'summary_frequency' => 'weekly']);
    expect($link->telegram_id)->toBe('10001')->and($link->announcements)->toBeFalse();
    expect(fn () => $service->claim($challenge->token_hash, '10001', 'Ulang'))->toThrow(ValidationException::class);
    expect(TelegramOutbox::where('link_id', $link->id)->count())->toBe(1);
});

it('rejects wrong password expired challenges and a different candidate', function (): void {
    $service = app(TelegramLinkService::class);
    expect(fn () => $service->begin($this->actor, 'wrong'))->toThrow(ValidationException::class);
    $start = $service->begin($this->actor, 'TelegramOnly#2026');
    $challenge = TelegramChallenge::findOrFail($start['id']);
    $service->claim($challenge->token_hash, '10001', 'Pertama');
    expect(fn () => $service->claim($challenge->token_hash, '10002', 'Kedua'))->toThrow(ValidationException::class);
    $this->travel(6)->minutes();
    expect(fn () => $service->claim($challenge->token_hash, '10001', 'Ulang'))->toThrow(ValidationException::class);
    expect(TelegramLink::count())->toBe(0);
});

it('enforces the one to one account link and rotates callback generation on reauthorization', function (): void {
    $link = ($this->connect)($this->actor, '10001');
    $generation = $link->generation;
    $next = ($this->connect)($this->actor, '10001');
    expect($next->id)->toBe($link->id)->and($next->generation)->not->toBe($generation);
    $other = User::factory()->create(['role' => 'warga', 'status' => UserStatus::Active, 'password' => 'TelegramOnly#2026']);
    expect(fn () => ($this->connect)($other, '10001'))->toThrow(ValidationException::class);
    expect(TelegramLink::count())->toBe(1);
    $this->assertDatabaseHas('telegram_links', ['user_id' => $this->actor->id, 'telegram_id' => '10001']);
});

it('postpones a telegram after repeated invalid link claims', function (): void {
    $service = app(TelegramLinkService::class);
    foreach (range(1, 5) as $attempt) {
        expect(fn () => $service->claim(str_repeat('a', 64), '30001', 'Coba '.$attempt))->toThrow(ValidationException::class);
    }
    expect(fn () => $service->claim(str_repeat('b', 64), '30001', 'Keenam'))->toThrow(
        fn (ValidationException $exception): bool => str_contains((string) $exception->errors()['link'][0], '15 menit'),
    );
    $start = $service->begin($this->actor, 'TelegramOnly#2026');
    expect(fn () => $service->claim(TelegramChallenge::findOrFail($start['id'])->token_hash, '30001', 'Sah'))->toThrow(ValidationException::class);
    expect(TelegramLink::count())->toBe(0);
});

it('separates notifications from expired transaction authorization and rejects changed accounts', function (): void {
    $link = ($this->connect)($this->actor, '10001');
    $link->forceFill(['authorized_until' => now()->subMinute()])->save();
    expect(app(TelegramAccess::class)->actor($link)->id)->toBe($this->actor->id);
    expect(fn () => app(TelegramAccess::class)->actor($link, true))->toThrow(ValidationException::class);
    $this->actor->forceFill(['password' => 'ChangedOnly#2026'])->save();
    expect(fn () => app(TelegramAccess::class)->actor($link))->toThrow(AuthorizationException::class);
});

it('revokes pending delivery and does not turn private messages into public messages', function (): void {
    $link = ($this->connect)($this->actor, '10001');
    app(TelegramLinkService::class)->revoke($this->actor, $this->actor, 'TelegramOnly#2026');
    expect(TelegramLink::count())->toBe(0);
    $message = TelegramOutbox::firstOrFail();
    expect($message->private)->toBeTrue()->and($message->link_id)->toBe($link->id)->and($message->status)->toBe('cancelled');
    Http::assertNothingSent();
});

it('denies disabled accounts and Admin as bot operators', function (): void {
    foreach (['admin', 'warga'] as $role) {
        $actor = User::factory()->create(['role' => $role, 'status' => $role === 'warga' ? UserStatus::Inactive : UserStatus::Active, 'password' => 'TelegramOnly#2026']);
        expect(fn () => app(TelegramLinkService::class)->begin($actor, 'TelegramOnly#2026'))->toThrow(AuthorizationException::class);
    }
});

it('authenticates webhook requests ignores groups and deduplicates accepted updates', function (): void {
    $payload = ['update_id' => 100, 'message' => ['from' => ['id' => 10001, 'is_bot' => false], 'chat' => ['id' => 10001, 'type' => 'private'], 'text' => '/menu']];
    $this->postJson('/telegram/webhook', $payload)->assertForbidden();
    $headers = ['X-Telegram-Bot-Api-Secret-Token' => str_repeat('s', 40)];
    $this->postJson('/telegram/webhook', $payload, $headers)->assertOk();
    $this->postJson('/telegram/webhook', $payload, $headers)->assertOk();
    expect(TelegramUpdate::count())->toBe(1);
    $payload['update_id'] = 101;
    $payload['message']['chat']['type'] = 'group';
    $this->postJson('/telegram/webhook', $payload, $headers)->assertOk();
    expect(TelegramUpdate::count())->toBe(1);
    app(ProcessTelegramUpdate::class)->handle(100);
    app(ProcessTelegramUpdate::class)->handle(100);
    expect(TelegramOutbox::count())->toBe(1);
    Http::assertNothingSent();
});

it('hashes the link challenge before storing the incoming update', function (): void {
    $start = app(TelegramLinkService::class)->begin($this->actor, 'TelegramOnly#2026');
    $raw = explode('link_', $start['url'])[1];
    $this->postJson('/telegram/webhook', ['update_id' => 100, 'message' => ['from' => ['id' => 10001, 'is_bot' => false], 'chat' => ['id' => 10001, 'type' => 'private'], 'text' => '/start link_'.$raw]], ['X-Telegram-Bot-Api-Secret-Token' => str_repeat('s', 40)])->assertOk();
    $update = TelegramUpdate::firstOrFail();
    expect($update->getRawOriginal('payload'))->not->toContain($raw);
    expect($update->payload['link_hash'])->toBe(hash('sha256', $raw));
    app(ProcessTelegramUpdate::class)->handle(100);
    expect(TelegramLink::count())->toBe(0)->and(TelegramChallenge::firstOrFail()->telegram_id)->toBe('10001');
});
