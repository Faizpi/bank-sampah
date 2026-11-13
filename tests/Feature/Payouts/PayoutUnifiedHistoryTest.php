<?php

declare(strict_types=1);

use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Livewire\Citizen\PayoutShow;
use App\Livewire\Citizen\WithdrawalHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    Payout::credit($this->owner, $this->officer);
    $this->cash = WithdrawalRequest::factory()->create(['customer_id' => $this->owner->id, 'requested_by_id' => $this->owner->id,
        'request_number' => 'WDR-OWN-CASH', 'status' => WithdrawalStatus::Paid]);
    $this->digital = app(PayoutService::class)->request($this->owner, Payout::request('bca'), (string) str()->uuid());
    $this->actingAs($this->owner);
});

it('keeps one withdrawal history with cash and digital choices inside the existing transaction navigation', function (): void {
    $screen = Livewire::test(WithdrawalHistory::class)->assertSet('method', 'tunai')->assertSee('Riwayat Pencairan')
        ->assertSee('Tunai')->assertSee('Digital (simulasi)')->assertSee($this->cash->request_number)
        ->assertDontSee($this->digital->request_number)->assertDontSee('Riwayat digital (simulasi)')
        ->assertSeeHtml(route('citizen.deposit-history'))->assertSeeHtml(route('citizen.grocery-history'))
        ->assertSeeHtml(route('citizen.withdrawal.receipt', $this->cash));
    $screen->call('selectMethod', 'digital')->assertSet('method', 'digital')->assertSee('Riwayat Pencairan')
        ->assertSee($this->digital->request_number)->assertSee('BCA (simulasi)')->assertDontSee($this->cash->request_number)
        ->assertSee(asset('images/payouts/bca.svg'), false)
        ->assertSeeHtml('aria-pressed="true"');
    $screen->call('selectMethod', 'tunai')->assertSee($this->cash->request_number)->assertDontSee($this->digital->request_number);
    expect(BalanceHold::count())->toBe(1)->and(LedgerEntry::count())->toBe(1);
    Http::assertNothingSent();
});

it('keeps each completed receipt with its own record and shows digital proof only after success', function (): void {
    $pending = WithdrawalRequest::factory()->create(['customer_id' => $this->owner->id, 'requested_by_id' => $this->owner->id,
        'request_number' => 'WDR-PENDING', 'status' => WithdrawalStatus::Waiting]);
    $cashHtml = Livewire::test(WithdrawalHistory::class)->html();
    $document = new DOMDocument;
    @$document->loadHTML($cashHtml);
    $xpath = new DOMXPath($document);
    $paid = $xpath->query('//article[@*[name()="wire:key"]="history-tunai-'.$this->cash->id.'"]')->item(0);
    $unpaid = $xpath->query('//article[@*[name()="wire:key"]="history-tunai-'.$pending->id.'"]')->item(0);
    expect($paid)->not->toBeNull()->and($unpaid)->not->toBeNull()
        ->and($xpath->query('.//a[contains(@href, "'.route('citizen.withdrawal.receipt', $this->cash).'")]', $paid)->length)->toBe(1)
        ->and($xpath->query('.//a[contains(., "Bukti pencairan")]', $unpaid)->length)->toBe(0);

    Livewire::withQueryParams(['metode' => 'digital'])->test(WithdrawalHistory::class)
        ->assertSee($this->digital->request_number)->assertDontSee('Bukti simulasi');
    $this->digital->forceFill(['status' => 'succeeded'])->save();
    Livewire::withQueryParams(['metode' => 'digital'])->test(WithdrawalHistory::class)
        ->assertSee('Bukti simulasi')->assertSeeHtml(route('citizen.payout.show', $this->digital));
    Http::assertNothingSent();
});

it('redirects the old digital history URL to a bookmarkable method on the single history page', function (): void {
    $this->get(route('citizen.payout.history'))->assertRedirect(route('citizen.withdrawal-history', ['metode' => 'digital']));
    $this->get(route('citizen.withdrawal-history', ['metode' => 'digital']))->assertOk()
        ->assertSee('Riwayat Pencairan')->assertSee($this->digital->request_number)->assertDontSee($this->cash->request_number);
    Http::assertNothingSent();
});

it('scopes digital records to the owner and applies number status and date filters', function (): void {
    $other = User::factory()->create(['role' => 'warga', 'status' => 'aktif']);
    CustomerProfile::factory()->create(['user_id' => $other->id, 'rt_id' => $this->owner->customerProfile->rt_id]);
    Payout::credit($other, $this->officer);
    $private = app(PayoutService::class)->request($other, Payout::request(), (string) str()->uuid());
    $this->digital->forceFill(['status' => 'uncertain', 'failure_code' => 'payout.transport'])->save();
    Livewire::withQueryParams(['metode' => 'digital'])->test(WithdrawalHistory::class)
        ->set('requestNumber', $this->digital->request_number)->set('status', 'uncertain')
        ->set('dateFrom', today()->toDateString())->set('dateUntil', today()->toDateString())
        ->assertSee($this->digital->request_number)->assertSee('Status sedang diperiksa')->assertDontSee($private->request_number)
        ->set('requestNumber', 'NOT-FOUND')->assertSee('Pencairan tidak ditemukan')->assertDontSee($this->digital->request_number);
    Http::assertNothingSent();
});

it('clears a method specific status and pagination when switching methods', function (): void {
    Livewire::test(WithdrawalHistory::class)->set('status', WithdrawalStatus::Paid->value)->call('setPage', 2)
        ->call('selectMethod', 'digital')->assertSet('status', '')->assertSet('paginators.page', 1)
        ->assertSee($this->digital->request_number)->set('status', 'processing')->assertSee($this->digital->request_number)
        ->call('selectMethod', 'tunai')->assertSet('status', '')->assertSee($this->cash->request_number);
});

it('keeps existing digital history readable when new digital submissions are disabled', function (): void {
    config()->set('payouts.enabled', false);
    $this->get(route('citizen.withdrawal-history', ['metode' => 'digital']))->assertOk()->assertSee($this->digital->request_number);
    Livewire::withQueryParams(['metode' => 'digital'])->test(WithdrawalHistory::class)->assertSee('Digital (simulasi)');
    Http::assertNothingSent();
});

it('keeps cash history working before the payout migration is installed', function (): void {
    Schema::shouldReceive('hasTable')->with('sandbox_payouts')->andReturnFalse();
    Livewire::test(WithdrawalHistory::class)->assertSet('method', 'tunai')->assertSee($this->cash->request_number)
        ->assertDontSee('Digital (simulasi)');
});

it('removes redundant warnings from the detail without exposing a cancellation action after dispatch', function (): void {
    $this->digital->forceFill(['status' => 'uncertain', 'failure_code' => 'payout.transport'])->save();
    Livewire::test(PayoutShow::class, ['payout' => $this->digital])->assertSee('Status sedang diperiksa')
        ->assertSee('Saldo masih disisihkan')->assertSee('Riwayat pencairan')
        ->assertSeeHtml(route('citizen.withdrawal-history', ['metode' => 'digital']))
        ->assertDontSee('Jangan mengajukan ulang')->assertDontSee('Batalkan sebelum diproses');
    Http::assertNothingSent();
});
