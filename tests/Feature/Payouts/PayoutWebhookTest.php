<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    Payout::credit($this->owner, User::where('username', 'petugas')->firstOrFail());
    $this->row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $this->headers = ['x-callback-token' => str_repeat('w', 40)];
    $this->event = ['event' => 'v3_payout.succeeded', 'created' => now()->toIso8601String(), 'business_id' => 'test-business', 'data' => ['payout_id' => 'po-test-'.$this->row->id, 'reference_id' => $this->row->reference_id]];
});

it('authenticates callbacks before storing data and never changes the ledger directly from a webhook', function (): void {
    $this->postJson(route('payout.webhook'), $this->event)->assertForbidden();
    expect(DB::table('sandbox_payout_events')->count())->toBe(0);
    $this->postJson(route('payout.webhook'), $this->event, $this->headers)->assertOk();
    $this->postJson(route('payout.webhook'), $this->event, $this->headers)->assertOk();
    expect(DB::table('sandbox_payout_events')->count())->toBe(1)->and(LedgerEntry::count())->toBe(1)->and($this->row->fresh()->balanceHold->status)->toBe('aktif');
    Http::assertNothingSent();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row))]);
    expect(app(PayoutWorker::class)->run())->toBe(1);
    expect($this->row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2);
    Http::assertSent(fn ($r): bool => $r->method() === 'GET');
});

it('does not trust a forged success status when authenticated provider lookup is still pending', function (): void {
    $this->postJson(route('payout.webhook'), $this->event, $this->headers)->assertOk();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row, 'ACCEPTED'))]);
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->status)->toBe('pending')->and($this->row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
});

it('reconciles reversed payments once without deleting the original debit', function (): void {
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row))]);
    app(PayoutWorker::class)->run();
    $original = $this->row->fresh()->ledger_entry_id;
    $event = [...$this->event, 'event' => 'v3_payout.reversed'];
    $this->postJson(route('payout.webhook'), $event, $this->headers)->assertOk();
    $this->postJson(route('payout.webhook'), $event, $this->headers)->assertOk();
    Payout::configure();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row, 'REVERSED'))]);
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->status)->toBe('reversed')->and($this->row->fresh()->ledger_entry_id)->toBe($original)
        ->and(LedgerEntry::count())->toBe(3)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
    $this->postJson(route('payout.webhook'), [...$event, 'created' => now()->addSecond()->toIso8601String()], $this->headers)->assertOk();
    app(PayoutWorker::class)->run();
    expect(LedgerEntry::count())->toBe(3);
});

it('does not let late failed events regress a successful debit or free its converted hold', function (): void {
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row))]);
    app(PayoutWorker::class)->run();
    $this->postJson(route('payout.webhook'), [...$this->event, 'event' => 'v3_payout.failed'], $this->headers)->assertOk();
    Payout::configure();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row, 'FAILED'))]);
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->status)->toBe('succeeded')->and($this->row->fresh()->failure_code)->toBe('STATUS_CONFLICT')
        ->and(LedgerEntry::count())->toBe(2)->and($this->row->fresh()->balanceHold->status)->toBe('dikonversi');
});

it('retains callbacks arriving during an API call for a subsequent lookup', function (): void {
    $row = $this->row;
    Http::fake(function () use ($row) {
        $this->postJson(route('payout.webhook'), $this->event, $this->headers)->assertOk();

        return Http::response(Payout::response($row, 'ACCEPTED'));
    });
    app(PayoutWorker::class)->run();
    expect(DB::table('sandbox_payout_events')->whereNull('processed_at')->count())->toBe(1);
    $this->travel(61)->seconds();
    Payout::configure();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('succeeded');
});

it('still settles a submitted payout after the owner account becomes inactive', function (): void {
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row, 'ACCEPTED'))]);
    app(PayoutWorker::class)->run();
    $this->owner->forceFill(['status' => 'nonaktif'])->save();
    $this->postJson(route('payout.webhook'), $this->event, $this->headers)->assertOk();
    Payout::configure();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row))]);
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2);
});
