<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Models\Reconciliation;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\AuditReconciliation\Services\FinancialReconciliationService;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Filament\Pages\Reconciliation as ReconciliationPage;
use App\Filament\Pages\TechnicalAuditRetentionPage;
use App\Filament\Pages\TechnicalSettingsPage;
use App\Filament\Resources\Groceries\Models\GroceryPackages\Pages\ManageGroceryPackages;
use App\Filament\Resources\Identity\Models\Users\Pages\ManageUsers as ManageUsersPage;
use App\Filament\Resources\Pickups\Models\PickupRequests\Pages\ManagePickupRequests;
use App\Livewire\Citizen\CustomerCard;
use App\Livewire\Citizen\PickupRequestForm;
use App\Livewire\Officer\CustomerIdentification;
use App\Livewire\Officer\DepositForm;
use App\Livewire\Officer\PickupTask;
use App\Livewire\Treasurer\Reports;
use App\Livewire\Treasurer\WithdrawalPayments;
use App\Models\User;
use App\Support\Auth\DemoCredentials;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->treasurer = User::where('username', 'bendahara')->firstOrFail();
    $this->type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->items = [['waste_type_id' => $this->type->id, 'condition_id' => $this->type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']];
    Filament::setCurrentPanel(Filament::getPanel('backoffice'));
});

it('creates a staff account and valid area in one atomic operation or creates nothing', function (): void {
    $data = ['name' => 'Pengurus uji', 'username' => 'pengurus.uji', 'password' => 'PasswordOnly#2026', 'password_confirmation' => 'PasswordOnly#2026', 'role' => 'petugas'];
    expect(fn () => app(ManageUsers::class)->create($this->admin, $data))->toThrow(ValidationException::class);
    expect(User::where('username', 'pengurus.uji')->exists())->toBeFalse();
    $this->actingAs($this->admin);
    Livewire::test(ManageUsersPage::class)->callAction('create', data: [...$data, 'phone' => '081234999999', 'service_areas' => [['service_area_id' => $this->officer->staffProfile->service_area_id, 'active_from' => today()->toDateString(), 'active_to' => null]]])->assertHasNoErrors();
    $created = User::where('username', 'pengurus.uji')->firstOrFail();
    expect($created->phone)->toBe('6281234999999')->and($created->staffProfile->serviceAreas()->count())->toBe(1);
});

it('clears old assignment form state when choosing a new staff role', function (): void {
    $this->actingAs($this->admin);
    $component = Livewire::test(ManageUsersPage::class)->mountTableAction('edit', $this->officer);
    $component->set('mountedActions.0.data.new_role', 'bendahara')->set('mountedActions.0.data.role_reason', 'Beralih tugas menjadi Bendahara');
    $component->callMountedAction()->assertHasErrors();
    expect($this->officer->fresh()->role->value)->toBe('petugas');
});

it('records a single Admin check with differences without changing ledger entries', function (): void {
    $service = app(FinancialReconciliationService::class);
    $record = $service->create($this->admin, CarbonImmutable::today(), 100);
    expect(fn () => $service->complete($this->admin, $record))->toThrow(ValidationException::class);
    $before = LedgerEntry::count();
    $completed = $service->complete($this->admin, $record, 'Selisih kas dicatat untuk pemeriksaan sumber.');
    expect($completed->status)->toBe(Reconciliation::STATUS_COMPLETED_WITH_DIFFERENCE)->and(LedgerEntry::count())->toBe($before);
    expect($service->complete($this->admin, $completed)->approved_at->toIso8601String())->toBe($completed->approved_at->toIso8601String());
});

it('requires a counted cash amount but no second administrator', function (): void {
    $service = app(FinancialReconciliationService::class);
    $record = $service->create($this->admin, CarbonImmutable::today(), null);
    expect(fn () => $service->complete($this->admin, $record))->toThrow(ValidationException::class);
    $service->setCashTotal($this->admin, $record, 0);
    expect($service->complete($this->admin, $record)->status)->toBe(Reconciliation::STATUS_COMPLETED);
});

it('serves the citizen service hub and keeps the Admin shared navigation in the Admin area', function (): void {
    $this->actingAs($this->citizen)->get('/warga/layanan')->assertOk()->assertSee('Minta penjemputan')->assertSee('Cairkan Saldo')->assertSee('Tukar saldo dengan sembako')->assertSee('Hitung perkiraan nilai');
    $this->actingAs($this->admin)->get('/profil/kata-sandi')->assertOk()->assertSee('Panel Admin')->assertDontSee('href="/dashboard/petugas"', false)->assertDontSee('profile_rt');
});

it('accepts comma weights and rejects malformed mixed separators', function (): void {
    $this->actingAs($this->officer);
    Livewire::test(DepositForm::class, ['customerId' => $this->citizen->id])->set('items', [[...$this->items[0], 'weight_kg' => '1,25']])->call('saveDraft')->assertHasNoErrors();
    Livewire::test(DepositForm::class, ['customerId' => $this->citizen->id])->set('items', [[...$this->items[0], 'weight_kg' => '1.2,5']])->call('saveDraft')->assertHasErrors('items.0.weight_kg');
});

it('submits pickup from one form with no estimates or photos', function (): void {
    $this->actingAs($this->citizen);
    Livewire::test(PickupRequestForm::class)->set('selectedDate', today()->toDateString())->set('items.0.waste_type_id', (string) $this->type->id)->call('submit')->assertHasNoErrors();
    expect(PickupRequest::where('customer_id', $this->citizen->id)->count())->toBe(1);
});

it('returns a clear no-result state rather than a silent customer choice', function (): void {
    $this->actingAs($this->officer);
    Livewire::test(CustomerIdentification::class)->set('search', 'Nama tidak ditemukan')->call('find')->assertSee('Warga tidak ditemukan')->assertSet('candidate', null);
});

it('never writes customer identity when its card is merely opened', function (): void {
    $profile = $this->citizen->customerProfile;
    $profile->forceFill(['customer_number' => null, 'qr_token_hash' => null, 'qr_token_encrypted' => null, 'joined_at' => null])->save();
    $this->actingAs($this->citizen);
    Livewire::test(CustomerCard::class)->assertSet('available', false);
    expect($profile->fresh()->customer_number)->toBeNull()->and($profile->fresh()->qr_token_hash)->toBeNull();
    app(ManageCustomerIdentity::class)->repair($this->admin, $this->citizen);
    expect($profile->fresh()->customer_number)->toStartWith('CST-');
    $this->assertDatabaseHas('audit_logs', ['action' => 'identity.customer.card_completed', 'actor_id' => $this->admin->id]);
});

it('only offers valid report statuses and makes invalid input visible', function (): void {
    $this->actingAs($this->treasurer);
    Livewire::test(Reports::class)->assertSet('statusOptions', ['sudah_dibayar' => 'Sudah dibayar'])->assertSee('Riwayat pencairan')
        ->set('status', 'menunggu_verifikasi')->call('refreshReport')->assertHasErrors('status')->assertSee('Status laporan tidak diizinkan.');
});

it('cannot alter rendered report totals through a client update', function (): void {
    $this->actingAs($this->treasurer);
    expect(fn () => Livewire::test(Reports::class)->set('metrics.total_value', 999999))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('keeps selected payments visible when another page or task tab is selected', function (): void {
    $deposits = app(DepositService::class);
    $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    $withdrawal = app(WithdrawalService::class)->request($this->citizen, ['amount' => 10000, 'pickup_location' => 'Loket', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    $this->actingAs($this->treasurer);
    Livewire::test(WithdrawalPayments::class)->call('select', $withdrawal->id)->set('queueTab', 'mine')->assertSee('Ringkasan sebelum penyerahan')->assertSee($withdrawal->request_number);
});

it('hides pickup execution and release controls while an Admin freeze is unresolved', function (): void {
    $service = app(PickupService::class);
    $pickup = $service->submit($this->citizen, ['service_area_id' => $this->officer->staffProfile->service_area_id, 'selected_date' => today()->toDateString(), 'address' => $this->citizen->customerProfile->address], [['waste_type_id' => $this->type->id]], [], (string) str()->uuid());
    $claimed = $service->claim($this->officer, $pickup);
    $frozen = $service->freezeException($this->admin, $claimed, 'Pemeriksaan klaim penjemputan', DemoCredentials::password());
    $this->actingAs($this->officer);
    Livewire::test(PickupTask::class, ['pickup' => $frozen])->assertSee('dikunci')->assertDontSee('Menuju Lokasi')->assertDontSee('Lepas tugas ke antrean');
});

it('filters pickup lists using the same predicates as the Admin work center', function (): void {
    $service = app(PickupService::class);
    $pickup = $service->submit($this->citizen, ['service_area_id' => $this->officer->staffProfile->service_area_id, 'selected_date' => today()->toDateString(), 'address' => $this->citizen->customerProfile->address], [['waste_type_id' => $this->type->id]], [], (string) str()->uuid());
    $claimed = $service->claim($this->officer, $pickup);
    $this->actingAs($this->admin);
    Livewire::test(ManagePickupRequests::class)->filterTable('queue', 'pickup_today')->assertCanSeeTableRecords([$claimed])->filterTable('queue', 'pickup_overdue')->assertCanNotSeeTableRecords([$claimed]);
});

it('denies inherited retention operations on the settings page', function (): void {
    $this->actingAs($this->admin);
    Livewire::test(TechnicalSettingsPage::class)->call('previewRetention')->assertStatus(403);
});

it('retains and displays invalid action reasons after queue rendering and filter changes', function (): void {
    $deposits = app(DepositService::class);
    $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    $withdrawal = app(WithdrawalService::class)->request($this->citizen, ['amount' => 10000, 'pickup_location' => 'Loket', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    app(ClaimedServiceWorkflow::class)->claim($this->treasurer, $withdrawal);
    $this->actingAs($this->treasurer);
    $screen = Livewire::test(WithdrawalPayments::class)->call('select', $withdrawal->id)->set('reason', 'x')->call('release')->assertHasErrors('reason');
    $message = $screen->errors()->first('reason');
    $screen->assertSee($message)->set('queueSearch', 'Siti')->assertSee($message);
    expect($screen->instance()->getErrorBag()->has('reason'))->toBeTrue();
});

it('retains photo validation errors after a failed completion renders', function (): void {
    $deposits = app(DepositService::class);
    $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    $withdrawal = app(WithdrawalService::class)->request($this->citizen, ['amount' => 10000, 'pickup_location' => 'Loket', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    app(ClaimedServiceWorkflow::class)->claim($this->treasurer, $withdrawal);
    $this->actingAs($this->treasurer);
    $screen = Livewire::test(WithdrawalPayments::class)->call('select', $withdrawal->id)->set('proof', UploadedFile::fake()->create('invalid.pdf', 1, 'application/pdf'))->call('complete')->assertHasErrors('proof');
    $screen->assertSee($screen->errors()->first('proof'));
    expect($withdrawal->fresh()->paid_at)->toBeNull();
});

it('returns to a valid page when its last claimed task is completed released or cancelled', function (string $action): void {
    $deposits = app(DepositService::class);
    for ($i = 0; $i < 2; $i++) {
        $deposits->finalize($this->officer, $deposits->createDraft($this->officer, $this->citizen), (string) str()->uuid(), $this->items);
    }
    $requests = [];
    for ($i = 0; $i < 11; $i++) {
        $request = app(WithdrawalService::class)->request($this->citizen, ['amount' => 10000, 'pickup_location' => 'Loket', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
        $requests[] = app(ClaimedServiceWorkflow::class)->claim($this->treasurer, $request);
    }
    $last = $requests[10];
    if ($action === 'confirmCancellation') {
        app(ClaimedServiceWorkflow::class)->cancel($this->citizen, $last, 'Warga meminta pembatalan pengajuan.');
    }
    $this->actingAs($this->treasurer);
    Livewire::test(WithdrawalPayments::class)->call('setPage', 2, 'serviceTasks')->call('select', $last->id)->set('reason', 'Belum diserahkan dan tugas dilepas.')->call($action)->assertHasNoErrors()->assertSet('paginators.serviceTasks', 1)->assertSee($requests[0]->request_number);
})->with(['complete', 'release', 'confirmCancellation']);

it('attaches cash counts and explanations to their own reconciliation record', function (): void {
    $service = app(FinancialReconciliationService::class);
    $first = $service->create($this->admin, CarbonImmutable::today(), 0);
    $second = $service->create($this->admin, CarbonImmutable::today(), 0);
    $this->actingAs($this->admin);
    $screen = Livewire::test(ReconciliationPage::class)
        ->set('cashCounts.'.$first->id, '100')->set('cashCounts.'.$second->id, '200')
        ->call('saveCashCount', $first->id)->call('saveCashCount', $second->id)->assertHasNoErrors()
        ->set('decisionReasons.'.$first->id, 'Penjelasan pertama berbeda dari kedua.')
        ->set('decisionReasons.'.$second->id, 'Penjelasan kedua berbeda dari pertama.')
        ->call('completeSnapshot', $first->id)->call('completeSnapshot', $second->id)->assertHasNoErrors();
    expect($first->fresh()->cash_total)->toBe(100)->and($second->fresh()->cash_total)->toBe(200)
        ->and($first->fresh()->notes)->toContain('Penjelasan pertama')->not->toContain('Penjelasan kedua')
        ->and($second->fresh()->notes)->toContain('Penjelasan kedua')->not->toContain('Penjelasan pertama');
});

it('rejects a consumed retention preview and locked metadata cannot be forged', function (): void {
    $this->actingAs($this->admin);
    $screen = Livewire::test(TechnicalAuditRetentionPage::class)->set('retentionBefore', '2020-01-01')->call('previewRetention')->call('executeRetention')->assertHasNoErrors();
    $screen->call('executeRetention')->assertHasErrors('retentionBefore');
    expect(fn () => Livewire::test(TechnicalAuditRetentionPage::class)->set('retentionPreviewBefore', '2020-01-01'))->toThrow(CannotUpdateLockedPropertyException::class);
});

it('rejects a changed audit candidate set before any deletion even when the count is unchanged', function (): void {
    $this->travelTo(CarbonImmutable::parse('2010-01-01'));
    app(AuditLogger::class)->record($this->admin, 'ui.old', $this->admin, [], [], (string) str()->uuid());
    $this->travelBack();
    $service = app(AuditRetentionService::class);
    $preview = $service->preview($this->admin, '2020-01-01');
    $service->execute($this->admin, '2020-01-01', expectedCandidates: $preview->candidateIds);
    $this->travelTo(CarbonImmutable::parse('2010-01-02'));
    $replacement = app(AuditLogger::class)->record($this->admin, 'ui.replacement', $this->admin, [], [], (string) str()->uuid());
    $this->travelBack();
    expect($service->preview($this->admin, '2020-01-01')->deletableCount)->toBe($preview->deletableCount);
    expect(fn () => $service->execute($this->admin, '2020-01-01', expectedCandidates: $preview->candidateIds))->toThrow(ValidationException::class);
    expect($replacement->fresh())->not->toBeNull();
});

it('accepts Indonesian formatted package values without truncating thousands', function (): void {
    $this->actingAs($this->admin);
    Livewire::test(ManageGroceryPackages::class)->callAction('create', data: ['code' => 'PK-LOCAL', 'name' => 'Paket lokal', 'contents' => 'Beras dan minyak', 'value' => '20.000', 'active_from' => null, 'active_until' => null])->assertHasNoErrors();
    expect(GroceryPackage::where('code', 'PK-LOCAL')->firstOrFail()->value)->toBe(20000);
});

it('keeps reconciliation difference errors associated with the record after an unrelated update', function (): void {
    $record = app(FinancialReconciliationService::class)->create($this->admin, CarbonImmutable::today(), 100);
    $this->actingAs($this->admin);
    $screen = Livewire::test(ReconciliationPage::class)->call('completeSnapshot', $record->id)->assertHasErrors('decisionReasons.'.$record->id);
    $message = $screen->errors()->first('decisionReasons.'.$record->id);
    $screen->set('notes', 'Catatan untuk pemeriksaan berikutnya.')->assertSee($message);
    expect($screen->instance()->getErrorBag()->has('decisionReasons.'.$record->id))->toBeTrue();
});
