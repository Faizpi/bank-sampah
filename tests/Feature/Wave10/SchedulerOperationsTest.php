<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Notifications\Events\NotificationRequested;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Domain\Operations\Services\ScheduledOperationsService;
use App\Domain\Operations\Services\SchedulerHeartbeat;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::forget(SchedulerHeartbeat::EXPIRE_KEY);
    Cache::forget(SchedulerHeartbeat::PURGE_KEY);
});

it('expires each eligible record through its terminal service exactly once across duplicate runs', function (): void {
    Event::fake([NotificationRequested::class]);
    $withdrawal = w10ExpiredWithdrawal();
    $grocery = w10ExpiredGrocery();
    $export = w10ExpiredExport();

    $first = app(ScheduledOperationsService::class)->expireEligible();
    $second = app(ScheduledOperationsService::class)->expireEligible();

    expect($first)->toBe(['withdrawals' => 1, 'groceries' => 1, 'exports' => 1])
        ->and($second)->toBe(['withdrawals' => 0, 'groceries' => 0, 'exports' => 0])
        ->and($withdrawal->refresh()->status)->toBe(WithdrawalStatus::Expired)
        ->and($withdrawal->balanceHold()->sole()->status)->toBe(BalanceHold::STATUS_RELEASED)
        ->and($grocery->refresh()->status)->toBe(GroceryStatus::Expired)
        ->and($grocery->balanceHold()->sole()->status)->toBe(BalanceHold::STATUS_RELEASED)
        ->and($export->refresh()->status)->toBe(ReportExportStatus::Expired)
        ->and($export->fresh()->path)->toBeNull()
        ->and(AuditLog::query()->where('action', 'withdrawal.expired')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'grocery.expired')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'report.export.expired')->count())->toBe(1);

    Event::assertDispatchedTimes(NotificationRequested::class, 2);
    expect(BalanceHold::query()->where('status', BalanceHold::STATUS_RELEASED)->count())->toBe(2);
});

it('excludes terminal and unexpired records from scheduled expiry', function (): void {
    $terminal = WithdrawalRequest::factory()->create([
        'status' => WithdrawalStatus::Paid,
        'expires_at' => now()->subMinute(),
    ]);
    $unexpired = GroceryRedemption::factory()->create([
        'status' => GroceryStatus::ReadyForPickup,
        'expires_at' => now()->addMinute(),
    ]);
    $expiredExport = w10ExpiredExport(status: ReportExportStatus::Expired);

    $result = app(ScheduledOperationsService::class)->expireEligible();

    expect($result)->toBe(['withdrawals' => 0, 'groceries' => 0, 'exports' => 0])
        ->and($terminal->refresh()->status)->toBe(WithdrawalStatus::Paid)
        ->and($unexpired->refresh()->status)->toBe(GroceryStatus::ReadyForPickup)
        ->and($expiredExport->refresh()->status)->toBe(ReportExportStatus::Expired)
        ->and(AuditLog::query()->whereIn('action', ['withdrawal.expired', 'grocery.expired', 'report.export.expired'])->count())->toBe(0);
});

it('bounds cleanup batches and writes one audit record per purged row', function (): void {
    config()->set('operations.scheduler.batch_size', 2);
    $actor = User::factory()->create();
    collect(range(1, 3))->each(fn (int $number): IdempotencyKey => IdempotencyKey::query()->create([
        'actor_id' => $actor->id,
        'scope' => 'w10.cleanup',
        'key' => 'expired-key-'.$number,
        'payload_hash' => hash('sha256', 'payload-'.$number),
        'status' => 'completed',
        'expires_at' => now()->subHour(),
    ]));
    collect(range(1, 3))->each(fn (int $number): NotificationDeliveryFailure => NotificationDeliveryFailure::query()->create([
        'dedupe_key' => 'w10-failure-'.$number,
        'recipient_id' => $actor->id,
        'type' => 'withdrawal.expired',
        'attempts' => 3,
        'last_error' => 'Delivery endpoint unavailable.',
        'last_attempted_at' => now()->subDays(8),
        'retry_after' => now()->subDay(),
    ]));

    $first = app(ScheduledOperationsService::class)->purgeExpiredOperationalRows();

    expect($first)->toBe(['idempotency_keys' => 2, 'notification_failures' => 2])
        ->and(IdempotencyKey::query()->count())->toBe(1)
        ->and(NotificationDeliveryFailure::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'operations.idempotency_key.purged')->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'operations.notification_failure.purged')->count())->toBe(2);

    $second = app(ScheduledOperationsService::class)->purgeExpiredOperationalRows();

    expect($second)->toBe(['idempotency_keys' => 1, 'notification_failures' => 1])
        ->and(IdempotencyKey::query()->count())->toBe(0)
        ->and(NotificationDeliveryFailure::query()->count())->toBe(0)
        ->and(AuditLog::query()->where('action', 'operations.idempotency_key.purged')->count())->toBe(3)
        ->and(AuditLog::query()->where('action', 'operations.notification_failure.purged')->count())->toBe(3);
});

it('orchestrates bounded expiration from the Artisan command and records only its heartbeat', function (): void {
    w10ExpiredWithdrawal();

    $this->artisan('operations:expire')
        ->expectsOutputToContain('Expired withdrawals: 1; groceries: 0; exports: 0.')
        ->assertExitCode(0);

    expect(Cache::has(SchedulerHeartbeat::EXPIRE_KEY))->toBeTrue()
        ->and(Cache::has(SchedulerHeartbeat::PURGE_KEY))->toBeFalse();
});

it('records a purge heartbeat after the purge command succeeds', function (): void {
    $this->artisan('operations:purge')->assertExitCode(0);

    expect(Cache::has(SchedulerHeartbeat::PURGE_KEY))->toBeTrue()
        ->and(Cache::has(SchedulerHeartbeat::EXPIRE_KEY))->toBeFalse();
});

it('uses Jakarta scheduler timing and overlap locks for expiry and cleanup commands', function (): void {
    $events = collect(app(Schedule::class)->events());
    $expiry = $events->first(fn (object $event): bool => str_contains((string) $event->command, 'operations:expire'));
    $cleanup = $events->first(fn (object $event): bool => str_contains((string) $event->command, 'operations:purge'));

    expect($expiry)->not->toBeNull()
        ->and($expiry->expression)->toBe('*/5 * * * *')
        ->and($expiry->timezone)->toBe('Asia/Jakarta')
        ->and($expiry->withoutOverlapping)->toBeTrue()
        ->and($cleanup)->not->toBeNull()
        ->and($cleanup->expression)->toBe('15 2 * * *')
        ->and($cleanup->timezone)->toBe('Asia/Jakarta')
        ->and($cleanup->withoutOverlapping)->toBeTrue();
});

it('defaults to synchronous queues and bounds optional database workers by configuration', function (): void {
    $database = config('queue.connections.database');
    $composer = file_get_contents(base_path('composer.json'));

    expect(config('queue.default'))->toBe('sync')
        ->and($database['max_jobs'])->toBe(25)
        ->and($database['max_time'])->toBe(55)
        ->and($database['tries'])->toBe(3)
        ->and(config('operations.queue.worker_mode'))->toBe('none')
        ->and(config('operations.scheduler.heartbeat_ttl_seconds'))->toBeBetween(60, 172800)
        ->and(config('operations.scheduler.heartbeat_freshness_seconds'))->toBeBetween(60, 172800)
        ->and($composer)->toBeString()
        ->and($composer)->not->toContain('queue:listen')
        ->and($composer)->not->toContain('horizon');
});

it('returns an anonymous generic non-cacheable health response without sensitive data', function (): void {
    config()->set([
        'app.key' => 'base64:'.base64_encode(str_repeat('h', 32)),
        'database.connections.sqlite.database' => 'C:\\private\\database.sqlite',
    ]);

    $response = $this->get('/health');

    $response
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('Pragma', 'no-cache')
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->toContain('max-age=0')
        ->and($response->getContent())
        ->not->toContain('health-test-secret')
        ->not->toContain('C:\\private\\database.sqlite')
        ->not->toContain(base_path())
        ->not->toContain(storage_path());
    $this->get('/up')->assertNotFound();
});

function w10ExpiredWithdrawal(): WithdrawalRequest
{
    $customer = User::factory()->create();
    $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
    $withdrawal = WithdrawalRequest::factory()->create([
        'customer_id' => $customer->id,
        'requested_by_id' => $customer->id,
        'status' => WithdrawalStatus::ReadyForPickup,
        'expires_at' => now()->subMinute(),
    ]);
    $hold = BalanceHold::query()->create([
        'hold_number' => 'HLD-W10-W-'.$withdrawal->id,
        'ledger_account_id' => $account->id,
        'source_type' => WithdrawalRequest::class,
        'source_id' => $withdrawal->id,
        'source_key' => 'w10-withdrawal-'.$withdrawal->id,
        'amount' => $withdrawal->amount,
        'status' => BalanceHold::STATUS_ACTIVE,
        'held_at' => now()->subHour(),
    ]);
    $withdrawal->forceFill(['balance_hold_id' => $hold->id])->save();

    return $withdrawal->fresh();
}

function w10ExpiredGrocery(): GroceryRedemption
{
    $customer = User::factory()->create();
    $account = LedgerAccount::query()->create(['user_id' => $customer->id, 'status' => 'aktif', 'currency' => 'IDR']);
    $package = GroceryPackage::query()->create([
        'code' => 'W10-PKG-'.$customer->id,
        'name' => 'Paket operasional W10',
        'contents' => 'Beras dan minyak',
        'value' => 20_000,
        'status' => 'aktif',
    ]);
    $redemption = GroceryRedemption::query()->create([
        'request_number' => 'GRC-W10-'.$customer->id,
        'customer_id' => $customer->id,
        'requested_by_id' => $customer->id,
        'grocery_package_id' => $package->id,
        'value_snapshot' => $package->value,
        'package_snapshot' => ['code' => $package->code, 'name' => $package->name, 'contents' => $package->contents, 'value' => $package->value],
        'source_type' => GrocerySource::Balance,
        'status' => GroceryStatus::ReadyForPickup,
        'expires_at' => now()->subMinute(),
    ]);
    $hold = BalanceHold::query()->create([
        'hold_number' => 'HLD-W10-G-'.$redemption->id,
        'ledger_account_id' => $account->id,
        'source_type' => GroceryRedemption::class,
        'source_id' => $redemption->id,
        'source_key' => 'w10-grocery-'.$redemption->id,
        'amount' => $redemption->value_snapshot,
        'status' => BalanceHold::STATUS_ACTIVE,
        'held_at' => now()->subHour(),
    ]);
    $redemption->forceFill(['balance_hold_id' => $hold->id])->save();

    return $redemption->fresh();
}

function w10ExpiredExport(ReportExportStatus $status = ReportExportStatus::Succeeded): ReportExport
{
    $requester = User::factory()->create();

    return ReportExport::query()->create([
        'uuid' => (string) str()->uuid(),
        'requester_id' => $requester->id,
        'report_type' => ReportType::Deposits,
        'filters' => [],
        'format' => ReportFormat::Csv,
        'status' => $status,
        'expires_at' => now()->subMinute(),
    ]);
}
