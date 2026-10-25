<?php

declare(strict_types=1);

use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Telegram\Services\SendTelegramOutbox;
use App\Domain\Telegram\Services\TelegramNotifications;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

beforeEach(function (): void {
    // Real commit tests cannot use the outer test transaction. Irreversible access
    // migrations are refreshed only on the database validated by tests/bootstrap.php.
    Artisan::call('migrate:fresh', ['--force' => true]);
    RefreshDatabaseState::$migrated = false;
    $this->travelTo(CarbonImmutable::parse('2026-10-25 09:00', 'Asia/Jakarta'));
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->link = Bot::link($this->citizen, '10001');
    TelegramOutbox::query()->delete();
    $this->credit = function (): void {
        $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
        $service = app(DepositService::class);
        $service->finalize($this->officer, $service->createDraft($this->officer, $this->citizen), (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50']]);
    };
});

afterEach(function (): void {
    RefreshDatabaseState::$migrated = false;
});

it('enqueues committed web notifications only after the transaction commits', function (): void {
    $this->link->forceFill(['transaction_notifications' => true])->save();
    DB::transaction(function (): void {
        ($this->credit)();
        expect(TelegramOutbox::where('category', 'transaction_notifications')->count())->toBe(0);
    });
    $count = TelegramOutbox::where('category', 'transaction_notifications')->count();
    expect($count)->toBeGreaterThan(0);
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'transaction_notifications')->count())->toBe($count);
    Http::assertNothingSent();
});

it('keeps transaction notifications off until the user opts in', function (): void {
    ($this->credit)();
    app(TelegramNotifications::class)->produce();

    expect($this->link->fresh()->transaction_notifications)->toBeFalse()
        ->and(TelegramOutbox::where('category', 'transaction_notifications')->count())->toBe(0);
});

it('never publishes a rolled back transaction', function (): void {
    try {
        DB::transaction(function (): void {
            ($this->credit)();
            throw new RuntimeException('Intentional test rollback');
        });
    } catch (RuntimeException) {
    }
    expect(LedgerEntry::count())->toBe(0)->and(TelegramOutbox::count())->toBe(0);
});

it('sends an opted in weekly summary once without extending transaction authorization', function (): void {
    ($this->credit)();
    $this->link->forceFill(['summaries' => true, 'authorized_until' => now()->subMinute()])->save();
    $before = $this->link->authorized_until->toIso8601String();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 09:00', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    app(TelegramNotifications::class)->produce();
    $summary = TelegramOutbox::where('category', 'summaries')->firstOrFail();
    expect($summary->payload['text'])->toContain('75.000')->and(TelegramOutbox::where('category', 'summaries')->count())->toBe(1);
    expect($this->link->fresh()->authorized_until->toIso8601String())->toBe($before);
});

it('rate limits reminders and rechecks the request before a delayed delivery', function (): void {
    ($this->credit)();
    $cash = app(WithdrawalService::class)->request($this->citizen, ['amount' => 20000, 'pickup_date' => today()->toDateString(), 'pickup_location' => 'Loket uji'], (string) str()->uuid());
    $this->link->forceFill(['reminders' => true])->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 09:30', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'reminders')->count())->toBe(1);
    $reminder = TelegramOutbox::where('category', 'reminders')->firstOrFail();
    app(WithdrawalService::class)->cancel($this->citizen, $cash);
    app(SendTelegramOutbox::class)->send(100);
    expect($reminder->fresh()->status)->toBe('cancelled');
});

it('queues the daily reminder only during the 09:00 hour', function (): void {
    ($this->credit)();
    app(WithdrawalService::class)->request($this->citizen, ['amount' => 20000, 'pickup_date' => today()->toDateString(), 'pickup_location' => 'Loket uji'], (string) str()->uuid());
    $this->link->forceFill(['reminders' => true])->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 10:30', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'reminders')->count())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-10-27 09:05', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'reminders')->count())->toBe(1);
});

it('keeps scheduled digests silent during quiet hours', function (): void {
    ($this->credit)();
    app(AnnouncementService::class)->publish($this->admin, app(AnnouncementService::class)->create($this->admin, 'Pengumuman uji', 'Jadwal bank sampah.', now()->toDateTimeString(), null));
    $this->link->forceFill(['summaries' => true, 'announcements' => true])->save();
    $this->travelTo(CarbonImmutable::parse('2026-10-26 20:30', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'summaries')->count())->toBe(0)
        ->and(TelegramOutbox::where('category', 'announcements')->count())->toBe(0);
    $this->travelTo(CarbonImmutable::parse('2026-11-02 09:10', 'Asia/Jakarta'));
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'summaries')->count())->toBe(1)
        ->and(TelegramOutbox::where('category', 'announcements')->count())->toBe(1);
});

it('requires separate announcement consent and checks publication again before sending', function (): void {
    $service = app(AnnouncementService::class);
    $announcement = $service->publish($this->admin, $service->create($this->admin, 'Pengumuman uji', 'Jadwal bank sampah.', now()->toDateTimeString(), null));
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'announcements')->count())->toBe(0);
    $this->link->forceFill(['announcements' => true])->save();
    app(TelegramNotifications::class)->produce();
    app(TelegramNotifications::class)->produce();
    expect(TelegramOutbox::where('category', 'announcements')->count())->toBe(1);
    $message = TelegramOutbox::where('category', 'announcements')->firstOrFail();
    $service->unpublish($this->admin, $announcement);
    app(SendTelegramOutbox::class)->send(100);
    expect($message->fresh()->status)->toBe('cancelled');
    Http::assertNothingSent();
});

it('does not expose unpublished or small group target progress', function (): void {
    ($this->credit)();
    $target = CollectionTarget::query()->create(['target_number' => 'TG-TARGET', 'name' => 'Target kelompok uji', 'purpose' => 'Uji privasi', 'period_start' => today()->subDay(), 'period_end' => today()->addWeek(), 'target_weight_kg' => 100, 'status' => 'aktif', 'is_public' => true, 'public_min_subjects' => 3, 'created_by' => $this->admin->id]);
    Bot::text('20000', '/menu');
    Bot::click('20000', 'Informasi umum');
    Bot::click('20000', 'Target sampah');
    expect(Bot::lastText('20000'))->not->toContain($target->name);
    $target->forceFill(['public_min_subjects' => 1])->save();
    Bot::text('20000', '/menu');
    Bot::click('20000', 'Informasi umum');
    Bot::click('20000', 'Target sampah');
    expect(Bot::lastText('20000'))->toContain($target->name, '50');
});
