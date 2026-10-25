<?php

declare(strict_types=1);

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Telegram\Models\TelegramConversation;
use App\Domain\Telegram\Models\TelegramUpdate;
use App\Domain\Telegram\Services\ProcessTelegramUpdate;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->citizenLink = Bot::link($this->citizen, '10001');
    $this->officerLink = Bot::link($this->officer, '10002');
    $this->treasurerLink = Bot::link($this->treasurer, '10003');
    $this->credit = function (): void {
        $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
        $service = app(DepositService::class);
        $service->finalize($this->officer, $service->createDraft($this->officer, $this->citizen), (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50']]);
    };
    $this->requestCash = function (): WithdrawalRequest {
        Bot::text('10001', '/menu');
        Bot::click('10001', 'Cairkan saldo');
        Bot::text('10001', '20.000');
        Bot::click('10001', 'Hari ini');
        Bot::click('10001', 'Gunakan alamat profil');
        Bot::click('10001', 'Ya, kirim pengajuan');

        return WithdrawalRequest::latest('id')->firstOrFail();
    };
    $this->requestPickup = function (): PickupRequest {
        Bot::text('10001', '/menu');
        Bot::click('10001', 'Jemput sampah');
        Bot::click('10001', 'Hari ini');
        Bot::click('10001', 'Gunakan alamat profil');
        Bot::click('10001', 'Aqua Botol');
        Bot::click('10001', 'Selesai pilih jenis');
        Bot::click('10001', 'Tinjau pengajuan');
        Bot::click('10001', 'Ya, kirim pengajuan');

        return PickupRequest::latest('id')->firstOrFail();
    };
});

it('reads balances without creating a ledger account or financial draft', function (): void {
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Saldo');
    expect(Bot::lastText('10001'))->toContain('Saldo tersedia: Rp 0');
    expect(LedgerAccount::count())->toBe(0)->and(Deposit::count())->toBe(0)->and(BalanceHold::count())->toBe(0);
});

it('supports public catalogue and estimates without an account or mutation', function (): void {
    Bot::text('20000', '/start');
    Bot::click('20000', 'Informasi umum');
    Bot::click('20000', 'Perkirakan nilai');
    Bot::click('20000', 'Aqua Botol');
    Bot::click('20000', 'Bersih dan Kering');
    Bot::text('20000', '1,25');
    expect(Bot::lastText('20000'))->toContain('Rp 1.875');
    expect(LedgerAccount::count())->toBe(0)->and(Deposit::count())->toBe(0);
});

it('submits a pickup without optional estimates or photos and safely replays an update', function (): void {
    $pickup = ($this->requestPickup)();
    expect($pickup->status)->toBe(PickupStatus::Waiting)->and($pickup->items()->count())->toBe(1);
    expect($pickup->address)->toBe($this->citizen->customerProfile->address);
    $last = TelegramUpdate::max('id');
    app(ProcessTelegramUpdate::class)->handle((int) $last);
    expect(PickupRequest::count())->toBe(1)->and(LedgerEntry::count())->toBe(0);
});

it('holds cash requests and releases cancellation before claim without an outgoing entry', function (): void {
    ($this->credit)();
    $cash = ($this->requestCash)();
    expect($cash->amount)->toBe(20000)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    expect(LedgerEntry::where('direction', LedgerEntry::DIRECTION_OUT)->count())->toBe(0);
    Bot::click('10001', 'Batal / minta pembatalan');
    Bot::click('10001', 'Ya, minta pembatalan');
    expect($cash->fresh()->status->value)->toBe('dibatalkan')->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
    expect(LedgerEntry::count())->toBe(1);
});

it('finishes cash payment once after an explicit handover confirmation', function (): void {
    ($this->credit)();
    $cash = ($this->requestCash)();
    Bot::text('10003', '/menu');
    Bot::click('10003', 'Antrean pembayaran');
    Bot::click('10003', $this->citizen->name.' · Rp 20.000');
    Bot::click('10003', 'Tunai tersedia — ambil pembayaran');
    Bot::click('10003', 'Uang sudah diserahkan');
    expect($cash->fresh()->paid_at)->toBeNull()->and(LedgerEntry::count())->toBe(1);
    $capability = Bot::button('10003', 'Ya, catat pembayaran');
    Bot::click('10003', 'Periksa lagi');
    expect(LedgerEntry::count())->toBe(1);
    Bot::click('10003', 'Uang sudah diserahkan');
    Bot::click('10003', 'Ya, catat pembayaran');
    expect($cash->fresh()->paid_at)->not->toBeNull()->and(LedgerEntry::count())->toBe(2);
    expect(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    Bot::update('10003', ['callback' => $capability, 'callback_id' => 'old-capability']);
    expect(LedgerEntry::count())->toBe(2);
});

it('freezes cancellation after cash claim until the claimant confirms no physical payment', function (): void {
    ($this->credit)();
    $cash = ($this->requestCash)();
    app(WithdrawalService::class)->claim($this->treasurer, $cash);
    Bot::click('10001', 'Batal / minta pembatalan');
    Bot::click('10001', 'Ya, minta pembatalan');
    expect($cash->fresh()->getAttribute('cancellation_requested_at'))->not->toBeNull();
    expect(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    Bot::text('10003', '/menu');
    Bot::click('10003', 'Tugas saya');
    Bot::click('10003', $this->citizen->name.' · Rp 20.000');
    Bot::click('10003', 'Konfirmasi belum diserahkan');
    Bot::click('10003', 'Belum diserahkan — batalkan');
    expect($cash->fresh()->status->value)->toBe('dibatalkan')->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
    expect(LedgerEntry::count())->toBe(1);
});

it('completes groceries through Telegram without mandatory preparation or photo', function (): void {
    ($this->credit)();
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Tukar sembako');
    $package = GroceryPackage::firstOrFail();
    Bot::click('10001', $package->name.' · Rp 50.000');
    Bot::click('10001', 'Ya, kirim pengajuan');
    $grocery = GroceryRedemption::firstOrFail();
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Sembako');
    Bot::click('10002', $this->citizen->name.' · Rp 50.000');
    Bot::click('10002', 'Paket tersedia — ambil tugas');
    Bot::click('10002', 'Paket sudah diserahkan');
    Bot::click('10002', 'Ya, catat penyerahan');
    expect($grocery->fresh()->status->value)->toBe('selesai')->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(25000);
    expect(LedgerEntry::count())->toBe(2);
});

it('weighs all four waste types and finalizes one direct deposit through chat', function (): void {
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Catat setoran');
    Bot::text('10002', $this->citizen->customerProfile->customer_number);
    expect(Deposit::count())->toBe(0);
    $candidate = $this->citizen->name.' · '.substr($this->citizen->customerProfile->customer_number, 0, 4).'****'.substr($this->citizen->customerProfile->customer_number, -2);
    Bot::click('10002', $candidate);
    foreach (WasteType::with('conditions')->orderBy('id')->get() as $index => $type) {
        if ($index > 0) {
            Bot::click('10002', 'Tambah jenis');
        }
        Bot::click('10002', $type->name);
        Bot::click('10002', $type->conditions->first()->name);
        Bot::text('10002', '1');
    }
    expect(LedgerEntry::count())->toBe(0);
    Bot::click('10002', 'Data benar — catat setoran');
    expect(Deposit::firstOrFail()->status)->toBe('final')->and(Deposit::firstOrFail()->items()->count())->toBe(4);
    expect(LedgerEntry::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(5000);
});

it('finishes a claimed pickup only after weighing and final deposit', function (): void {
    $pickup = ($this->requestPickup)();
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Penjemputan');
    Bot::click('10002', $this->citizen->name);
    Bot::click('10002', 'Ambil tugas');
    Bot::click('10002', 'Mulai perjalanan');
    Bot::click('10002', 'Sampah sudah dijemput');
    expect($pickup->fresh()->status)->toBe(PickupStatus::PickedUp)->and(LedgerEntry::count())->toBe(0);
    Bot::click('10002', 'Catat hasil timbang');
    Bot::click('10002', 'Aqua Botol');
    Bot::click('10002', 'Bersih dan Kering');
    Bot::text('10002', '2');
    Bot::click('10002', 'Data benar — catat setoran');
    expect($pickup->fresh()->status)->toBe(PickupStatus::Completed)->and($pickup->fresh()->deposit_id)->not->toBeNull();
    expect(LedgerAccount::firstOrFail()->availableBalance())->toBe(3000)->and(LedgerEntry::count())->toBe(1);
});

it('rejects another Telegram using a private capability and rejects stale conversation buttons', function (): void {
    ($this->credit)();
    Bot::text('10001', '/menu');
    $button = Bot::button('10001', 'Cairkan saldo');
    Bot::text('10002', '/menu');
    Bot::update('10002', ['callback' => $button, 'callback_id' => 'forged']);
    expect(TelegramConversation::whereKey('10002')->firstOrFail()->state)->toBe('menu');
    expect(WithdrawalRequest::count())->toBe(0);
    Bot::click('10001', 'Cairkan saldo');
    Bot::text('10001', '20000');
    $dateButton = Bot::button('10001', 'Hari ini');
    Bot::text('10001', '/menu');
    Bot::update('10001', ['callback' => $dateButton, 'callback_id' => 'stale']);
    expect(WithdrawalRequest::count())->toBe(0)->and(TelegramConversation::whereKey('10001')->firstOrFail()->state)->toBe('menu');
});

it('opens weighs recaps and closes only an assigned mobile service', function (): void {
    $profile = $this->citizen->customerProfile;
    $service = app(MobileServiceService::class)->create($this->admin, $profile->rt->rw_id, $profile->rt_id, 'Titik uji', now()->subMinutes(10)->format('Y-m-d H:i'), now()->addHour()->format('Y-m-d H:i'), 2, '', [$this->officer->id], WasteType::pluck('id')->all());
    app(MobileServiceService::class)->transition($this->admin, $service, MobileServiceStatus::Published);
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Layanan keliling');
    Bot::click('10002', 'Titik uji');
    Bot::click('10002', 'Buka layanan');
    Bot::click('10002', 'Catat setoran keliling');
    Bot::text('10002', $profile->customer_number);
    Bot::click('10002', $this->citizen->name.' · '.substr($profile->customer_number, 0, 4).'****'.substr($profile->customer_number, -2));
    Bot::click('10002', 'Aqua Botol');
    Bot::click('10002', 'Bersih dan Kering');
    Bot::text('10002', '2');
    Bot::click('10002', 'Data benar — catat setoran');
    expect($service->fresh()->served_count)->toBe(1)->and(Deposit::firstOrFail()->mobile_service_id)->toBe($service->id);
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Layanan keliling');
    Bot::click('10002', 'Titik uji');
    Bot::click('10002', 'Tinjau dan tutup');
    expect(Bot::lastText('10002'))->toContain('3.000', '1 setoran');
    Bot::click('10002', 'Ya, tutup layanan');
    expect($service->fresh()->status)->toBe(MobileServiceStatus::Closed)->and(LedgerEntry::count())->toBe(1);
});
