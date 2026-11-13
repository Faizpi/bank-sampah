<?php

declare(strict_types=1);

use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Filament\Pages\SandboxPayouts;
use App\Livewire\Treasurer\Reports;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    Payout::credit($this->owner, User::where('username', 'petugas')->firstOrFail());
    $this->digital = app(PayoutService::class)->request($this->owner, Payout::request('gopay'), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(['error_code' => 'API_VALIDATION_ERROR'], 400)]);
    app(PayoutWorker::class)->run();
    $this->cash = WithdrawalRequest::factory()->create(['customer_id' => $this->owner->id, 'requested_by_id' => $this->owner->id,
        'request_number' => 'WDR-OWN-REPORT', 'payer_id' => $this->treasurer->id,
        'status' => WithdrawalStatus::Paid, 'paid_at' => now(), 'amount' => 10000]);
    $this->filters = ['start' => today()->toDateString(), 'end' => today()->addDay()->toDateString()];
});

it('switches cash and digital in the same treasurer report without changing financial history', function (): void {
    $this->actingAs($this->treasurer);
    $screen = Livewire::test(Reports::class)->assertSee('Riwayat pencairan')->assertSee('Tunai')->assertSee('Digital (simulasi)')
        ->assertSee($this->cash->request_number)->assertDontSee($this->digital->request_number)->assertSet('metrics.total_amount', 10000)
        ->assertDontSee('Pantauan digital (simulasi)');
    $screen->call('selectMethod', 'digital')->assertSet('reportType', 'digital_payouts')->assertSee($this->digital->request_number)
        ->assertDontSee($this->cash->request_number)->assertSee('Simulasi gagal')->assertSee('Sistem')
        ->assertSet('metrics.payout_count', 1)->assertSet('metrics.succeeded_amount', 0)->assertSee('Unduh Excel')
        ->assertDontSee('payout.http_')->assertDontSee('API_VALIDATION_ERROR')->assertDontSee('recipient.address')->assertDontSee('routing');
    $screen->call('selectMethod', 'tunai')->assertSee($this->cash->request_number)->assertDontSee($this->digital->request_number);
    expect(LedgerEntry::count())->toBe(1);
    Http::assertSentCount(1);
});

it('keeps cash payments owner scoped and digital reports scoped to active task areas', function (): void {
    $otherTreasurer = User::factory()->create(['role' => 'bendahara', 'status' => 'aktif']);
    WithdrawalRequest::factory()->create(['customer_id' => $this->owner->id, 'requested_by_id' => $this->owner->id,
        'request_number' => 'WDR-OTHER-PAYER', 'payer_id' => $otherTreasurer->id, 'status' => WithdrawalStatus::Paid, 'paid_at' => now()]);
    $this->actingAs($this->treasurer);
    Livewire::test(Reports::class)->assertSee('WDR-OWN-REPORT')->assertDontSee('WDR-OTHER-PAYER');
    StaffServiceArea::where('staff_profile_user_id', $this->treasurer->id)->update(['active_to' => today()->subDay()]);
    Livewire::withQueryParams(['metode' => 'digital'])->test(Reports::class)->assertDontSee($this->digital->request_number)
        ->assertSet('metrics.payout_count', 0);
    $this->actingAs($this->admin);
    Livewire::test(Reports::class, ['surface' => 'backoffice'])->set('reportType', 'digital_payouts')
        ->assertSee($this->digital->request_number)->assertSet('metrics.payout_count', 1);
});

it('keeps method date and status filters on the shared report including zero padded months', function (): void {
    $this->actingAs($this->treasurer);
    Livewire::withQueryParams(['metode' => 'digital'])->test(Reports::class)->assertSet('reportType', 'digital_payouts')
        ->set('status', 'succeeded')->call('refreshReport')->assertDontSee($this->digital->request_number)
        ->set('status', 'failed')->call('refreshReport')->assertSee($this->digital->request_number)
        ->set('month', '01')->set('year', '2026')->call('setPeriod', 'month')->assertHasNoErrors()
        ->assertSet('start', '2026-01-01')->assertSet('end', '2026-01-31')->assertDontSee($this->digital->request_number)
        ->call('selectMethod', 'tunai')->assertSet('status', '')->assertSet('period', 'month');
});

it('redirects old monitor URLs into the appropriate role workspace', function (): void {
    $this->actingAs($this->treasurer)->get(route('payout.monitor'))->assertRedirect(route('treasurer.reports', ['metode' => 'digital']));
    $this->actingAs($this->admin)->get(route('payout.monitor'))->assertRedirect(SandboxPayouts::getUrl());
});

it('opens payout details inside the backoffice table without treasury links or full destination numbers', function (): void {
    Filament::setCurrentPanel(Filament::getPanel('backoffice'));
    $this->actingAs($this->admin);
    $actionSpec = TestAction::make('detail')->table($this->digital);
    $component = Livewire::test(SandboxPayouts::class)->mountAction($actionSpec)->assertActionMounted($actionSpec)
        ->assertDontSeeHtml('href="'.route('payout.monitor').'"')->assertDontSeeHtml('href="'.route('treasurer.reports').'"');
    // Filament 5 sends modal HTML as a separate Livewire partial, not the table's HTML.
    $action = $component->instance()->getMountedAction();
    expect($action->shouldOpenModal())->toBeTrue()->and($action->getModalHeading())->toBe('Rincian pencairan digital (simulasi)');
    $details = $action->getModalContent()->render();
    expect($details)->toContain($this->digital->request_number, 'Layanan pencairan belum dapat memproses pengajuan ini.', $this->digital->maskedNumber())
        ->not->toContain($this->digital->recipient['number'], 'payout.http_', 'API_VALIDATION_ERROR', 'routing', 'recipient.address');
    Http::assertSentCount(1);
});

it('exports masked simulation records as private Excel without counting failed requests as paid', function (): void {
    Storage::fake('media_private');
    $export = app(ReportExportService::class)->export($this->treasurer, 'digital_payouts', $this->filters, 'xlsx');
    expect($export->isAvailable())->toBeTrue();
    $zip = new ZipArchive;
    expect($zip->open(Storage::disk('media_private')->path($export->path)))->toBeTrue();
    $sheet = $zip->getFromName('xl/worksheets/sheet1.xml');
    $zip->close();
    expect($sheet)->toContain($this->digital->request_number, 'GoPay (simulasi)', 'Simulasi gagal', 'Sistem otomatis', 'tidak mengirim uang riil')
        ->not->toContain($this->digital->recipient['number'], $this->cash->request_number);
    $summary = app(ReportQueryService::class)->aggregate($this->treasurer, $this->filters, ReportType::DigitalPayouts);
    expect($summary['succeeded_amount'])->toBe(0)->and($summary['payout_count'])->toBe(1)->and(LedgerEntry::count())->toBe(1);
});

it('keeps simulation and executor markers even when selecting only some export columns', function (): void {
    Storage::fake('media_private');
    $export = app(ReportExportService::class)->export($this->admin, 'digital_payouts', $this->filters, 'csv', ['request_number', 'amount']);
    $csv = Storage::disk('media_private')->get($export->path);
    expect($csv)->toContain('Lingkungan', 'Pelaksana', 'Xendit Test Mode', 'Sistem otomatis')->not->toContain($this->digital->recipient['number']);
});

it('denies citizen and officer access to digital report and export services', function (): void {
    foreach ([$this->owner, User::where('username', 'petugas')->firstOrFail()] as $actor) {
        expect(fn () => app(ReportQueryService::class)->query($actor, $this->filters, 'digital_payouts'))
            ->toThrow(AuthorizationException::class);
    }
});
