<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutAccess;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Filament\Pages\SandboxPayouts;
use App\Livewire\Citizen\PayoutForm;
use App\Livewire\Citizen\PayoutShow;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
    Payout::credit($this->owner, $this->officer);
});

it('requires a server verified review before creating a payout from the web', function (): void {
    $this->actingAs($this->owner);
    $screen = Livewire::test(PayoutForm::class)->set('channel', 'dana')->set('recipientName', 'Siti Aminah')->set('accountNumber', '081234567890')->set('amount', '20.000');
    $screen->call('submit')->assertHasErrors('payout');
    expect(SandboxPayout::count())->toBe(0);
    $screen->call('review')->assertHasNoErrors()->assertSee('Konfirmasi pencairan simulasi');
    $screen->set('accountNumber', '081234567891')->call('submit')->assertHasErrors('payout');
    expect(SandboxPayout::count())->toBe(0);
    $screen->call('review')->call('submit')->assertHasNoErrors()->assertRedirect(route('citizen.payout.show', 1));
    expect(SandboxPayout::firstOrFail()->recipient['number'])->toBe('081234567891');
    expect(WithdrawalRequest::count())->toBe(0)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000);
    Http::assertNothingSent();
});

it('renders the six local original logos and labels all choices as simulation', function (): void {
    $this->actingAs($this->owner);
    $screen = Livewire::test(PayoutForm::class)->assertSee('Mode uji coba')->assertSee('BCA')->assertSee('Mandiri')->assertSee('BRI')->assertSee('BNI')->assertSee('DANA')->assertSee('GoPay');
    foreach (app(PayoutChannels::class)->all() as $row) {
        expect(is_file(public_path($row['logo'])))->toBeTrue();
        $screen->assertSee(asset($row['logo']), false);
    }
});

it('normalizes Indonesian e wallet numbers without changing leading zeros on bank accounts', function (): void {
    $channels = app(PayoutChannels::class);
    expect($channels->normalizeNumber('dana', '+62 812-3456-7890'))->toBe('081234567890')
        ->and($channels->normalizeNumber('gopay', '6281234567890'))->toBe('081234567890')
        ->and($channels->normalizeNumber('bca', '0123456789'))->toBe('0123456789');
});

it('validates provider name limits before creating any hold', function (): void {
    foreach (['Siti', str_repeat('A', 51).' Aminah', 'Siti '.str_repeat('A', 51)] as $name) {
        expect(fn () => app(PayoutService::class)->request($this->owner, [...Payout::request(), 'name' => $name], (string) str()->uuid()))->toThrow(ValidationException::class);
    }
    expect(SandboxPayout::count())->toBe(0)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
});

it('keeps unavailable routing destinations disabled and refuses them at service level', function (): void {
    config()->set('payouts.routing.dana', '');
    $this->actingAs($this->owner);
    Livewire::test(PayoutForm::class)->set('channel', 'dana')->set('recipientName', 'Siti Aminah')->set('accountNumber', '081234567890')->set('amount', '20000')->call('review')->assertHasErrors('channel');
    expect(SandboxPayout::count())->toBe(0);
});

it('does not expose another customers payout through web detail or history', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $other = User::factory()->create(['role' => 'warga', 'status' => 'aktif']);
    $this->actingAs($other)->get(route('citizen.payout.show', $row))->assertNotFound();
    $this->get(route('citizen.payout.history'))->assertOk()->assertDontSee($row->request_number);
});

it('displays provider settlement as an automatic simulation rather than physical cash handover', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    $this->actingAs($this->owner);
    Livewire::test(PayoutShow::class, ['payout' => $row->fresh()])->assertSee('Simulasi berhasil')->assertSee('Otomatis oleh sistem')->assertSee('bukan struk resmi')->assertDontSee('Waktu serah fisik')->assertDontSee('Batalkan sebelum diproses');
});

it('keeps production and mismatched database configurations unavailable', function (): void {
    config()->set('payouts.enabled', false);
    expect(PayoutAccess::configured())->toBeFalse();
    $this->actingAs($this->owner)->get(route('citizen.payout.create'))->assertNotFound();
    config()->set(['payouts.enabled' => true, 'payouts.secret_key' => 'xnd_production_TEST_NOT_REAL']);
    expect(PayoutAccess::configured())->toBeFalse();
});

it('keeps unconfigured installations safe before the additive migration is installed', function (): void {
    Schema::shouldReceive('hasTable')->with('sandbox_payouts')->andReturnFalse();
    expect(PayoutAccess::configured())->toBeFalse();
    $this->actingAs(User::where('username', 'admin')->firstOrFail());
    expect(SandboxPayouts::canAccess())->toBeFalse();
    $this->actingAs($this->owner)->get(route('citizen.payout.create'))->assertNotFound();
    $this->get(route('citizen.payout.history'))->assertNotFound();
});
