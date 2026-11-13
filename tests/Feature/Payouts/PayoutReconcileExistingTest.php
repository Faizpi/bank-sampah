<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $owner = User::where('username', 'warga')->firstOrFail();
    Payout::credit($owner, User::where('username', 'petugas')->firstOrFail());
    $this->row = app(PayoutService::class)->request($owner, Payout::request('bca'), (string) str()->uuid());
    $this->row->forceFill(['status' => 'uncertain', 'failure_code' => 'payout.response_unconfirmed:source_amount'])->save();
    $this->provider = 'po-test-'.$this->row->id;
});

it('reconnects a pending existing payout with GET only and later settles it once', function (): void {
    $response = Payout::response($this->row, 'REQUESTED');
    unset($response['source_amount']);
    Http::fake(['api.xendit.co/*' => Http::response($response)]);
    $this->artisan('payouts:reconcile-existing', ['request' => $this->row->request_number, 'provider' => $this->provider])->assertSuccessful();
    expect($this->row->fresh()->status)->toBe('pending')->and($this->row->fresh()->provider_id)->toBe($this->provider)
        ->and($this->row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET' && str_ends_with($request->url(), '/'.$this->provider));

    $this->travel(2)->minutes();
    Payout::configure();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($this->row->fresh(), 'SUCCEEDED'))]);
    app(PayoutWorker::class)->run();
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    $this->artisan('payouts:reconcile-existing', ['request' => $this->row->request_number, 'provider' => $this->provider])->assertFailed();
    expect(LedgerEntry::count())->toBe(2);
});

it('rejects mismatched provider responses without saving an ID or touching the hold', function (string $field): void {
    $response = Payout::response($this->row, 'SUCCEEDED');
    match ($field) {
        'reference' => $response['reference_id'] = (string) str()->uuid(),
        'provider' => $response['payout_id'] = 'po-other',
        'amount' => $response['destination_amount'] = 1,
        'source' => $response['source_amount'] = 1,
        'account' => $response['recipient']['account_details']['account_number'] = 'wrong',
    };
    Http::fake(['api.xendit.co/*' => Http::response($response)]);
    $this->artisan('payouts:reconcile-existing', ['request' => $this->row->request_number, 'provider' => $this->provider])->assertFailed();
    expect($this->row->fresh()->provider_id)->toBeNull()->and($this->row->fresh()->status)->toBe('uncertain')
        ->and($this->row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
})->with(['reference', 'provider', 'amount', 'source', 'account']);

it('applies an already successful provider payout once without submitting another payout', function (): void {
    $response = Payout::response($this->row, 'SUCCEEDED');
    $response['source_amount'] = null;
    Http::fake(['api.xendit.co/*' => Http::response($response)]);
    $this->artisan('payouts:reconcile-existing', ['request' => $this->row->request_number, 'provider' => $this->provider])->assertSuccessful();
    expect($this->row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2)
        ->and($this->row->fresh()->balanceHold->status)->toBe('dikonversi');
    $this->artisan('payouts:reconcile-existing', ['request' => $this->row->request_number, 'provider' => $this->provider])->assertFailed();
    expect(LedgerEntry::count())->toBe(2);
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET');
});

it('accepts a creation response without source amount only when destination and identity match', function (): void {
    $this->row->forceFill(['status' => 'queued'])->save();
    $response = Payout::response($this->row, 'REQUESTED');
    unset($response['source_amount']);
    Http::fake(['api.xendit.co/*' => Http::response($response)]);
    app(PayoutWorker::class)->run();
    expect($this->row->fresh()->provider_id)->toBe($this->provider)->and($this->row->fresh()->status)->toBe('pending')
        ->and($this->row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
});
