<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramOutbox;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    Bot::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    Bot::link($this->owner, '10001');
    Bot::link($this->officer, '10002');
    Bot::link($this->treasurer, '10003');
    Payout::credit($this->owner, $this->officer);
});

it('submits each digital destination from Telegram only after confirmation and settles in shared history', function (string $channel, string $label): void {
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Digital (simulasi)');
    Bot::click('10001', $label.' (simulasi)');
    Bot::text('10001', 'Siti Aminah');
    Bot::text('10001', Payout::request($channel)['number']);
    Bot::text('10001', '20000');
    expect(Bot::lastText('10001'))->toContain('Tidak ada uang riil', $label);
    expect(SandboxPayout::count())->toBe(0)->and(LedgerEntry::count())->toBe(1);
    $capability = Bot::button('10001', 'Konfirmasi pencairan simulasi');
    Bot::click('10001', 'Konfirmasi pencairan simulasi');
    $row = SandboxPayout::firstOrFail();
    expect($row->channel)->toBe($channel)->and($row->consent_version)->toBe('payout-sandbox-v1')
        ->and($row->consented_at)->not->toBeNull()->and(WithdrawalRequest::count())->toBe(0);
    Bot::update('10001', ['callback' => $capability, 'callback_id' => 'replayed']);
    expect(SandboxPayout::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    Bot::fakeHttp(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => ['message_id' => 11]]), 'api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    Bot::click('10001', 'Periksa status');
    expect(Bot::lastText('10001'))->toContain('Simulasi berhasil', 'Tidak ada dana riil');
    $this->actingAs($this->owner)->get(route('citizen.payout.history'))->assertOk()->assertSee($row->request_number)->assertSee('Simulasi berhasil');
    expect(LedgerEntry::count())->toBe(2);
})->with([['bca', 'BCA'], ['mandiri', 'Mandiri'], ['bri', 'BRI'], ['bni', 'BNI'], ['dana', 'DANA'], ['gopay', 'GoPay']]);

it('cancels a web request from Telegram without an outflow and protects private buttons', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Riwayat');
    Bot::click('10001', 'Riwayat digital (simulasi)');
    Bot::click('10001', 'Rincian #1');
    $button = Bot::button('10001', 'Batalkan sebelum diproses');
    Bot::text('10002', '/menu');
    Bot::update('10002', ['callback' => $button, 'callback_id' => 'forged']);
    expect($row->fresh()->status)->toBe('queued');
    Bot::click('10001', 'Batalkan sebelum diproses');
    Bot::click('10001', 'Ya, batalkan simulasi');
    expect($row->fresh()->status)->toBe('cancelled')->and(LedgerEntry::count())->toBe(1)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
});

it('lets the treasurer monitor but never claim digital transactions', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Bot::text('10003', '/menu');
    Bot::click('10003', 'Pantau digital (simulasi)');
    Bot::click('10003', 'Rincian #1');
    expect(Bot::lastText('10003'))->toContain($row->request_number, 'otomatis oleh sistem');
    $message = TelegramOutbox::where('telegram_id', '10003')->where('kind', 'text')->latest('sequence')->firstOrFail();
    expect(json_encode($message->payload))->not->toContain('payout.cancel', 'Ambil pembayaran');
    expect(TelegramConversation::whereKey('10003')->firstOrFail()->state)->toBe('menu');
});
