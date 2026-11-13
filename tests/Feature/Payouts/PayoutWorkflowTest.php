<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    Payout::credit($this->owner, $this->officer);
});

it('creates a self service hold without a treasurer cash request or plaintext recipient', function (): void {
    $key = (string) str()->uuid();
    $service = app(PayoutService::class);
    $row = $service->request($this->owner, Payout::request(), $key);
    expect($row->status)->toBe('queued')->and(WithdrawalRequest::count())->toBe(0)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000)
        ->and(LedgerEntry::count())->toBe(1)->and(BalanceHold::count())->toBe(1);
    expect($row->getRawOriginal('recipient'))->not->toContain('081234567890', 'Siti Aminah');
    expect($service->request($this->owner, Payout::request(), $key)->id)->toBe($row->id);
    expect(fn () => $service->request($this->owner, [...Payout::request(), 'amount' => 30000], $key))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

it('supports only the six configured research destinations', function (string $channel): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request($channel), (string) str()->uuid());
    expect($row->channel)->toBe($channel)->and($row->request_payload['recipient']['account_details']['account_number'])->toBe($row->recipient['number']);
})->with(['bca', 'mandiri', 'bri', 'bni', 'dana', 'gopay']);

it('settles exactly once from an authenticated successful API response without a treasurer', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    expect(app(PayoutWorker::class)->run())->toBe(1);
    expect(app(PayoutWorker::class)->run())->toBe(0);
    expect($row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000)
        ->and($row->fresh()->balanceHold->status)->toBe(BalanceHold::STATUS_CONVERTED);
    Http::assertSent(fn ($r): bool => $r->method() === 'POST' && $r->hasHeader('idempotency-key', $row->provider_key)
        && $r['reference_id'] === $row->reference_id && $r->hasHeader('api-version', '2025-09-01'));
    test()->assertDatabaseHas('audit_logs', ['action' => 'payout.succeeded', 'actor_type' => 'system']);
});

it('settles an accepted payout from an authenticated lookup when the provider masks recipient account details', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request('gopay'), (string) str()->uuid());
    $created = Payout::response($row, 'ACCEPTED');
    Http::fake(['api.xendit.co/*' => Http::response($created)]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('pending')->and($row->fresh()->provider_id)->toBe($created['payout_id'])
        ->and($row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);

    $this->travel(2)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::lookupResponseWithMaskedDestination($row, 'SUCCEEDED'))]);
    expect(app(PayoutWorker::class)->run())->toBe(1);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('succeeded')->and($row->fresh()->ledger_entry_id)->not->toBeNull()
        ->and($row->fresh()->balanceHold->status)->toBe(BalanceHold::STATUS_CONVERTED)
        ->and(LedgerEntry::count())->toBe(2)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET' && str_ends_with($request->url(), '/'.$created['payout_id']));
    test()->assertDatabaseHas('audit_logs', ['action' => 'payout.succeeded', 'actor_type' => 'system']);
});

it('keeps the hold and rejects an authenticated lookup with a mismatched amount', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request('gopay'), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row, 'ACCEPTED'))]);
    app(PayoutWorker::class)->run();
    $this->travel(2)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response([...Payout::lookupResponse($row), 'destination_amount' => 1])]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->provider_id)->not->toBeNull()
        ->and($row->fresh()->balanceHold->status)->toBe('aktif')->and($row->fresh()->ledger_entry_id)->toBeNull()
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000)->and(LedgerEntry::count())->toBe(1);
});

it('releases the hold once on a definitive provider failure', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response([...Payout::response($row, 'FAILED'), 'failure_code' => 'INVALID_DESTINATION'])]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('failed')->and($row->fresh()->balanceHold->status)->toBe('dilepas')
        ->and(LedgerEntry::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
});

it('does not release or blindly resubmit a payout after an ambiguous transport timeout', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $calls = 0;
    Http::fake(static function () use (&$calls): never {
        $calls++;
        throw new ConnectionException('Sensitive transport details must never be persisted.');
    });
    app(PayoutWorker::class)->run();
    app(PayoutWorker::class)->run();
    expect($calls)->toBe(1)->and($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->failure_code)->toBe('payout.transport')
        ->and($row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
    expect(fn () => app(PayoutService::class)->cancel($this->owner, $row))->toThrow(ValidationException::class);
});

it('rejects a mismatched provider amount without creating a debit', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response([...Payout::response($row), 'destination_amount' => 1])]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('uncertain')->and(LedgerEntry::count())->toBe(1)->and($row->fresh()->balanceHold->status)->toBe('aktif');
});

it('records an outflow and a compensating inflow when reversal is the first verified status', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row, 'REVERSED'))]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('reversed')->and(LedgerEntry::count())->toBe(3)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
});

it('does not let staff request payouts or allow production keys', function (): void {
    expect(fn () => app(PayoutService::class)->request($this->officer, Payout::request(), (string) str()->uuid()))->toThrow(AuthorizationException::class);
    config()->set('payouts.secret_key', 'xnd_production_FAKE_NOT_FOR_USE');
    expect(PayoutAccess::configured())->toBeFalse();
    expect(fn () => app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid()))->toThrow(ValidationException::class);
    Http::assertNothingSent();
});

it('permits cancellation only before dispatch and leaves no cash transaction', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    app(PayoutService::class)->cancel($this->owner, $row);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('cancelled')->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000)
        ->and(WithdrawalRequest::count())->toBe(0)->and(SandboxPayout::count())->toBe(1);
    Http::assertNothingSent();
});

it('does not starve queued submissions behind older pending payouts', function (): void {
    $rows = collect(range(1, 3))->map(fn () => app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid()));
    Http::fake(function ($request) use ($rows) {
        $row = $rows->first(fn ($row): bool => $row->reference_id === $request['reference_id']);

        return Http::response(Payout::response($row, $row->id === $rows->last()->id ? 'SUCCEEDED' : 'ACCEPTED'));
    });
    app(PayoutWorker::class)->process($rows[0]->id);
    app(PayoutWorker::class)->process($rows[1]->id);
    $this->travel(2)->minutes();
    app(PayoutWorker::class)->run(limit: 1);
    expect($rows[2]->fresh()->status)->toBe('succeeded')->and($rows[0]->fresh()->status)->toBe('pending');
});
