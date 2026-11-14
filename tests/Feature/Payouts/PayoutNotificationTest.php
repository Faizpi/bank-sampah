<?php

declare(strict_types=1);

use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Services\TelegramNotifications;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;
use Tests\Support\TelegramHarness as Bot;

beforeEach(function (): void {
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    Bot::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $officer = User::where('username', 'petugas')->firstOrFail();
    $this->link = Bot::link($this->owner, '10001', ['transaction_notifications' => true]);
    Payout::credit($this->owner, $officer);
    TelegramOutbox::query()->delete();
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

it('queues one owner guarded Telegram notification for each committed payout status', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $messages = TelegramOutbox::where('category', 'transaction_notifications')->get()->filter(fn ($row): bool => data_get($row->payload, 'guards.0.kind') === 'payout')->values();
    expect($messages)->toHaveCount(1)->and($messages->first()->payload['guards'])->toBe([['kind' => 'payout', 'id' => $row->id]]);
    Bot::fakeHttp(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    app(TelegramNotifications::class)->produce();
    app(TelegramNotifications::class)->produce();
    $messages = TelegramOutbox::where('category', 'transaction_notifications')->get()->filter(fn ($row): bool => data_get($row->payload, 'guards.0.kind') === 'payout')->values();
    expect($messages)->toHaveCount(2)->and($messages->last()->payload['text'])->toContain('Pencairan berhasil', 'Tidak ada dana riil')
        ->not->toContain('081234567890');
});

it('keeps payout Telegram guards owner scoped when notification links use transaction codes', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $notification = Notification::query()->where('recipient_id', $this->owner->id)->latest('id')->firstOrFail();
    expect($notification->reference)->toBe('/warga/pencairan-digital/'.$row->request_number);
    $message = TelegramOutbox::query()->where('category', 'transaction_notifications')->latest('created_at')->firstOrFail();
    expect($message->payload['guards'])->toBe([['kind' => 'payout', 'id' => $row->id]]);
});
