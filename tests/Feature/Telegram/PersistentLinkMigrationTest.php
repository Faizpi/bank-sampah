<?php

declare(strict_types=1);

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Support\TelegramHarness as Bot;

it('upgrades existing links without changing ownership generation or notification consent', function (): void {
    // MySQL DDL commits implicitly: do not put this migration check in a test transaction.
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
    Bot::configure();
    $actor = User::factory()->create(['role' => 'warga', 'status' => UserStatus::Active]);
    $link = Bot::link($actor, '10001', ['transaction_notifications' => true, 'announcements' => true]);
    $migration = require database_path('migrations/2026_10_28_100000_make_telegram_link_access_persistent.php');
    $migration->down();
    DB::table('telegram_links')->where('id', $link->id)->update(['authorized_until' => now()->subDay(), 'last_activity_at' => now()->subDay()]);
    $before = DB::table('telegram_links')->where('id', $link->id)->first();

    $migration->up();

    $after = DB::table('telegram_links')->where('id', $link->id)->first();
    expect($before)->not->toBeNull();
    expect($after)->not->toBeNull();
    expect($after->id)->toBe($before->id)->and($after->user_id)->toBe($before->user_id)
        ->and($after->telegram_id)->toBe($before->telegram_id)->and($after->generation)->toBe($before->generation)
        ->and($after->account_fingerprint)->toBe($before->account_fingerprint)
        ->and($after->transaction_notifications)->toBe($before->transaction_notifications)
        ->and($after->announcements)->toBe($before->announcements)
        ->and($after->authorized_until)->toBeNull()->and($after->last_activity_at)->toBeNull();
    expect(app(TelegramAccess::class)->actor(TelegramLink::findOrFail($link->id))->id)->toBe($actor->id);

    $migration->down();
    expect(DB::table('telegram_links')->where('id', $link->id)->value('authorized_until'))->not->toBeNull();
    $migration->up();
    expect(TelegramLink::findOrFail($link->id)->generation)->toBe($link->generation);
});
