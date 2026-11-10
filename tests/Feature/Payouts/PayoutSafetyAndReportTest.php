<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Livewire\Treasurer\PayoutMonitor;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    Payout::credit($this->owner, $this->officer);
});

it('keeps digital settlement separate from physical cash reconciliation', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    $check = app(FinancialReconciliationService::class)->create($this->admin, CarbonImmutable::today('Asia/Jakarta'), 0);
    expect($check->withdrawal_total)->toBe(0)->and($check->cash_total)->toBe(0)
        ->and($check->closing_total)->toBe(55000)->and($check->difference)->toBe(0);
    $digital = $check->items->firstWhere('item_type', 'payout_ledger');
    expect($digital->expected_total)->toBe(20000)->and($digital->actual_total)->toBe(20000)
        ->and($check->items->firstWhere('item_type', 'cash_disbursement')->expected_total)->toBe(0);
});

it('keeps ordinary reconciliation available before the sandbox tables exist', function (): void {
    Schema::shouldReceive('hasTable')->with('sandbox_payouts')->andReturnFalse();
    $check = app(FinancialReconciliationService::class)->create($this->admin, CarbonImmutable::today('Asia/Jakarta'), 0);
    expect($check->items)->toHaveCount(5)->and($check->closing_total)->toBe(75000)->and($check->difference)->toBe(0);
});

it('scopes monitor and masked CSV to current treasurer areas while admin sees all', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $this->owner->forceFill(['name' => '=DANGEROUS()'])->save();
    $filters = ['start' => today()->toDateString(), 'end' => today()->toDateString()];
    $csv = $this->actingAs($this->treasurer)->get(route('payout.report', $filters))->assertOk()->streamedContent();
    expect($csv)->toContain($row->request_number, 'Sandbox', "'=DANGEROUS()", 'Sistem otomatis')->not->toContain('081234567890');
    StaffServiceArea::where('staff_profile_user_id', $this->treasurer->id)->update(['active_to' => today()->subDay()]);
    $this->get(route('payout.monitor'))->assertOk()->assertDontSee($row->request_number);
    expect($this->get(route('payout.report', $filters))->streamedContent())->not->toContain($row->request_number);
    $this->actingAs($this->admin)->get(route('payout.report', $filters))->assertOk();
    expect($this->get(route('payout.report', $filters))->streamedContent())->toContain($row->request_number);
});

it('rejects citizen and officer reports and renders invalid date errors without crashing', function (): void {
    foreach ([$this->owner, $this->officer] as $actor) {
        $this->actingAs($actor)->get(route('payout.monitor'))->assertForbidden();
        $this->get(route('payout.report'))->assertForbidden();
    }
    $this->actingAs($this->treasurer);
    Livewire::test(PayoutMonitor::class)->set('end', 'not-a-date')->assertHasErrors('end');
    $this->getJson(route('payout.report', ['start' => '2026-11-10', 'end' => '2026-11-01']))->assertUnprocessable();
});

it('rejects database edits to identity consent and provider request snapshots', function (string $field, mixed $value): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    expect(fn () => DB::table('sandbox_payouts')->where('id', $row->id)->update([$field => $value]))->toThrow(QueryException::class);
})->with([['amount', 1], ['environment', 'live'], ['request_payload', '{}'], ['consent_version', 'forged'], ['provider_key', 'forged']]);

it('preserves financial history and prevents spending beyond available balance', function (): void {
    $row = app(PayoutService::class)->request($this->owner, [...Payout::request(), 'amount' => 60000], (string) str()->uuid());
    expect(fn () => app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid()))->toThrow(ValidationException::class);
    expect(SandboxPayout::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(15000);
    expect(fn () => DB::table('sandbox_payouts')->where('id', $row->id)->delete())->toThrow(QueryException::class);
});

it('releases an unsubmitted request when the account is deactivated before dispatch', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $this->owner->forceFill(['status' => 'nonaktif'])->save();
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('failed')->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
    Http::assertNothingSent();
});

it('recovers an uncertain submission with the exact same persisted key and body only on operator request', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(static function (): never {
        throw new ConnectionException('timeout');
    });
    app(PayoutWorker::class)->run();
    $this->artisan('payouts:recover', ['id' => $row->id, '--reference' => $row->reference_id])->assertFailed();
    $this->travel(2)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    $this->artisan('payouts:recover', ['id' => $row->id, '--reference' => 'wrong'])->assertFailed();
    Http::assertNothingSent();
    $this->artisan('payouts:recover', ['id' => $row->id, '--reference' => $row->reference_id])->assertSuccessful();
    Http::assertSent(fn ($r): bool => $r->data() === $row->request_payload && $r->hasHeader('idempotency-key', $row->provider_key));
    expect($row->fresh()->status)->toBe('succeeded')->and(SandboxPayout::count())->toBe(1)->and(LedgerEntry::count())->toBe(2);
});
