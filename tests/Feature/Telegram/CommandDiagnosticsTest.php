<?php

declare(strict_types=1);

use App\Domain\Telegram\Services\TelegramApi;
use App\Domain\Telegram\Services\TelegramApiFailure;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\URL;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Bot::configure();
    URL::forceRootUrl('https://sindangheula.example');
    URL::forceScheme('https');
});

it('shows the webhook destination stage and useful Telegram rejection without leaking credentials', function (): void {
    $token = (string) config('telegram.bot_token');
    $secret = (string) config('telegram.webhook_secret');
    Bot::fakeHttp([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => config('telegram.bot_username')]]),
        '*/setWebhook' => Http::response(['ok' => false, 'error_code' => 400, 'description' => 'Bad Request: bad webhook: Failed to resolve host. token='.$token.' secret_token='.$secret.' '.rawurlencode($token)], 400),
    ]);

    expect(Artisan::call('telegram:webhook'))->toBe(1);
    $output = Artisan::output();
    expect($output)->toContain('Alamat webhook: https://sindangheula.example/telegram/webhook', 'Tahap: pemasangan webhook', 'telegram.http_400', 'Alasan Telegram: Bad Request: bad webhook: Failed to resolve host.')
        ->not->toContain($token, $secret, rawurlencode($token));
    Http::assertSentCount(2);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/setWebhook')
        && $request['url'] === 'https://sindangheula.example/telegram/webhook'
        && $request['secret_token'] === $secret
        && $request['allowed_updates'] === ['message', 'callback_query']
        && $request['drop_pending_updates'] === false);
});

it('keeps the normal webhook success and removal operations intact', function (): void {
    Bot::fakeHttp([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => config('telegram.bot_username')]]),
        '*/setWebhook' => Http::response(['ok' => true, 'result' => true]),
        '*/deleteWebhook' => Http::response(['ok' => true, 'result' => true]),
    ]);

    expect(Artisan::call('telegram:webhook'))->toBe(0);
    expect(Artisan::output())->toContain('Konfigurasi webhook diperbarui.');
    expect(Artisan::call('telegram:webhook', ['--remove' => true]))->toBe(0);
    expect(Artisan::output())->toContain('Tahap: penghapusan webhook');
    Http::assertSentCount(3);
    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/deleteWebhook') && $request['drop_pending_updates'] === false);
});

it('distinguishes an unset webhook from a successful API connectivity check', function (): void {
    Bot::fakeHttp([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => config('telegram.bot_username')]]),
        '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => ['url' => '', 'pending_update_count' => 6]]),
    ]);

    expect(Artisan::call('telegram:diagnose', ['--network' => true]))->toBe(0);
    expect(Artisan::output())->toContain('[OK] identitas bot', 'Update menunggu di Telegram: 6', '[INFO] Webhook di Telegram belum terpasang.');
    Http::assertSentCount(2);
});

it('shows a matching webhook without claiming historical delivery errors are current failures', function (): void {
    Bot::fakeHttp([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => config('telegram.bot_username')]]),
        '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => [
            'url' => 'https://sindangheula.example/telegram/webhook', 'pending_update_count' => 0,
            'last_error_message' => 'Wrong response from the webhook: 503 Service Unavailable',
        ]]),
    ]);

    expect(Artisan::call('telegram:diagnose', ['--network' => true]))->toBe(0);
    expect(Artisan::output())->toContain('[OK] kecocokan alamat webhook', 'Kesalahan pengiriman terakhir: Wrong response from the webhook: 503 Service Unavailable');
});

it('redacts credentials and query parameters from webhook information and errors', function (): void {
    $token = (string) config('telegram.bot_token');
    $secret = (string) config('telegram.webhook_secret');
    Bot::fakeHttp([
        '*/getMe' => Http::response(['ok' => true, 'result' => ['username' => config('telegram.bot_username')]]),
        '*/getWebhookInfo' => Http::response(['ok' => true, 'result' => [
            'url' => 'https://private-user:private-password@old.example/hook?token='.$token.'#'.$secret,
            'last_error_message' => '<error>Bad Request:</error>'."\r\n".'https://api.telegram.org/bot'.$token.'/setWebhook?secret_token='.$secret.' password=private-password',
        ]]),
    ]);

    expect(Artisan::call('telegram:diagnose', ['--network' => true]))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Webhook di Telegram: https://old.example/hook', '[WARN] kecocokan alamat webhook', 'Kesalahan pengiriman terakhir: Bad Request:')
        ->not->toContain($token, $secret, 'private-user', 'private-password', '<error>', '?secret_token=');
});

it('shows a safe Telegram error when network diagnostics are rejected', function (): void {
    Bot::fakeHttp(['*/getMe' => Http::response(['ok' => false, 'error_code' => 401, 'description' => 'Unauthorized'], 401)]);

    expect(Artisan::call('telegram:diagnose', ['--network' => true]))->toBe(1);
    expect(Artisan::output())->toContain('telegram.http_401', 'Alasan Telegram: Unauthorized');
    Http::assertSentCount(1);
});

it('does not expose an HTTP transport exception through command output', function (): void {
    $token = (string) config('telegram.bot_token');
    Bot::fakeHttp(static function () use ($token): never {
        throw new ConnectionException('https://api.telegram.org/bot'.$token.'/getMe');
    });

    expect(Artisan::call('telegram:webhook'))->toBe(1);
    expect(Artisan::output())->toContain('telegram.transport')->not->toContain($token, 'ConnectionException');
});

it('keeps malformed API descriptions empty and never includes an arbitrary response body', function (): void {
    foreach ([['description' => ['unexpected' => 'private-body']], ['description' => null]] as $fields) {
        Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => false, 'error_code' => 400, ...$fields], 400)]);
        try {
            app(TelegramApi::class)->call('getMe', []);
            test()->fail('Expected a safe API failure.');
        } catch (TelegramApiFailure $error) {
            expect($error->description)->toBe('')->and($error->getMessage())->toBe('telegram.http_400')->and($error->getPrevious())->toBeNull();
        }
    }
});

it('bounds diagnostic descriptions and masks other bot credentials', function (): void {
    $otherToken = '999999:UNRELATED_SYNTHETIC_TOKEN_12345';
    $text = TelegramApiFailure::safeDescription('Bad webhook https://api.telegram.org/bot'.$otherToken.'/setWebhook?private=query '.str_repeat('x', 700));

    expect(mb_strlen($text))->toBeLessThanOrEqual(500);
    expect($text)->not->toContain($otherToken, '?private=query');
    expect(TelegramApiFailure::safeUrl('https://name:password@host.example/webhook?secret=private#fragment'))->toBe('https://host.example/webhook');
});
