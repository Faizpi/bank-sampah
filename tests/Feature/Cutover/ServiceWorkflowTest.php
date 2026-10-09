<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\Auth\DemoCredentials;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    $this->warga = User::where('username', 'warga')->firstOrFail();
    $this->petugas = User::where('username', 'petugas')->firstOrFail();
    $this->bendahara = User::where('username', 'bendahara')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $depositService = app(DepositService::class);
    $deposit = $depositService->createDraft($this->petugas, $this->warga);
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->items = [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']];
    $this->deposit = $depositService->finalize($this->petugas, $deposit, (string) str()->uuid(), $this->items);
    $this->workflow = app(ClaimedServiceWorkflow::class);
});

function cutoverWithdrawal($test): WithdrawalRequest
{
    return app(WithdrawalService::class)->request($test->warga, ['amount' => 20000, 'pickup_location' => 'Bank sampah desa', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
}

function cutoverGrocery($test): GroceryRedemption
{
    return app(GroceryService::class)->request($test->warga, ['package_id' => GroceryPackage::firstOrFail()->id], (string) str()->uuid());
}

it('finalizes any routine deposit without photos exactly once', function (): void {
    expect($this->deposit->status)->toBe('final');
    $again = app(DepositService::class)->finalize($this->petugas, $this->deposit, $this->deposit->idempotency_key, $this->items);
    expect($again->id)->toBe($this->deposit->id)->and(LedgerEntry::count())->toBe(1);
});

it('queues cash without approval then claims and settles exactly once without a photo', function (): void {
    $request = cutoverWithdrawal($this);
    expect($request->status)->toBe(WithdrawalStatus::Waiting)->and($request->balanceHold->isActive())->toBeTrue()->and(LedgerEntry::count())->toBe(1);
    $claimed = $this->workflow->claim($this->bendahara, $request);
    expect($claimed->status)->toBe(WithdrawalStatus::Processing)->and($claimed->payer_id)->toBe($this->bendahara->id)->and($claimed->paid_at)->toBeNull();
    $key = (string) str()->uuid();
    $paid = $this->workflow->complete($this->bendahara, $claimed, null, $key);
    $retried = $this->workflow->complete($this->bendahara, $request, null, $key);
    expect($paid->status)->toBe(WithdrawalStatus::Paid)->and($paid->proof_media_id)->toBeNull()->and($retried->receipt_ledger_entry_id)->toBe($paid->receipt_ledger_entry_id)->and(LedgerEntry::count())->toBe(2);
});

it('rejects changed completion attachment and does not create another outflow', function (): void {
    $request = $this->workflow->claim($this->bendahara, cutoverWithdrawal($this));
    $key = (string) str()->uuid();
    $this->workflow->complete($this->bendahara, $request, null, $key);
    expect(fn () => $this->workflow->complete($this->bendahara, $request, UploadedFile::fake()->image('different.jpg'), $key))->toThrow(ValidationException::class);
    expect(LedgerEntry::count())->toBe(2);
});

it('releases a claim without releasing the hold', function (): void {
    $request = $this->workflow->claim($this->bendahara, cutoverWithdrawal($this));
    $released = $this->workflow->release($this->bendahara, $request, 'Uang dipastikan belum diserahkan.');
    expect($released->status)->toBe(WithdrawalStatus::Waiting)->and($released->payer_id)->toBeNull()->and($released->balanceHold->isActive())->toBeTrue();
    expect(fn () => $this->workflow->complete($this->bendahara, $request, null, (string) str()->uuid()))->toThrow(ValidationException::class);
    expect(LedgerEntry::count())->toBe(1);
});

it('rejects another claimant admin disabled user and revoked area at completion', function (string $case): void {
    $request = $this->workflow->claim($this->bendahara, cutoverWithdrawal($this));
    $actor = $this->bendahara;
    if ($case === 'admin') {
        $actor = $this->admin;
    } elseif ($case === 'other') {
        $actor = User::factory()->create(['role' => UserRole::Treasurer]);
        StaffProfile::create(['user_id' => $actor->id, 'staff_number' => 'STAFF-OTHER', 'service_area_id' => $request->service_area_id]);
    } elseif ($case === 'inactive') {
        $this->bendahara->fresh()->forceFill(['status' => UserStatus::Inactive])->save();
    } else {
        $this->bendahara->staffProfile->serviceAreas()->delete();
    }
    expect(fn () => $this->workflow->complete($actor, $request, null, (string) str()->uuid()))->toThrow($case === 'other' ? ValidationException::class : AuthorizationException::class);
    expect(LedgerEntry::count())->toBe(1)->and($request->fresh()->paid_at)->toBeNull();
})->with(['admin', 'other', 'inactive', 'area_revoked']);

it('allows a citizen cancellation before payment even after claim and releases once', function (): void {
    $request = $this->workflow->claim($this->bendahara, cutoverWithdrawal($this));
    $cancelled = $this->workflow->cancel($this->warga, $request, 'Warga membatalkan sebelum pembayaran.');
    expect($cancelled->status)->toBe(WithdrawalStatus::Processing)->and($cancelled->balanceHold->isActive())->toBeTrue();
    $cancelled = $this->workflow->cancel($this->bendahara, $request, 'Pelaksana memastikan uang belum diserahkan.');
    $again = $this->workflow->cancel($this->warga, $request, 'Warga membatalkan sebelum pembayaran.');
    expect($cancelled->status)->toBe(WithdrawalStatus::Cancelled)->and($again->balanceHold->status)->toBe('dilepas')->and(LedgerEntry::count())->toBe(1);
});

it('can hand over groceries immediately after claim without preparation or photo', function (): void {
    $request = $this->workflow->claim($this->petugas, cutoverGrocery($this));
    $done = $this->workflow->complete($this->petugas, $request, null, (string) str()->uuid());
    expect($done->status)->toBe(GroceryStatus::Completed)->and($done->handover_actor_id)->toBe($this->petugas->id)->and($done->proof_media_id)->toBeNull()->and(LedgerEntry::count())->toBe(2);
});

it('can optionally mark a claimed package ready for later pickup', function (): void {
    $request = $this->workflow->claim($this->petugas, cutoverGrocery($this));
    $ready = $this->workflow->ready($this->petugas, $request);
    expect($ready->status)->toBe(GroceryStatus::ReadyForPickup)->and($ready->balanceHold->isActive())->toBeTrue()->and(LedgerEntry::count())->toBe(1);
});

it('unavailable groceries release the hold without an outflow', function (): void {
    $request = cutoverGrocery($this);
    $done = $this->workflow->cancel($this->petugas, $request, 'Paket fisik sedang tidak tersedia.', true);
    expect($done->status)->toBe(GroceryStatus::Unavailable)->and($done->balanceHold->status)->toBe('dilepas')->and(LedgerEntry::count())->toBe(1);
});

it('locks uncertain handoffs and admin resolves at most one conversion', function (bool $handed): void {
    $request = $this->workflow->claim($this->bendahara, cutoverWithdrawal($this));
    $this->workflow->reportException($this->bendahara, $request, 'Respons pencatatan hilang, perlu diperiksa.');
    expect(fn () => $this->workflow->complete($this->bendahara, $request, null, (string) str()->uuid()))->toThrow(ValidationException::class);
    $reason = 'Fakta dikonfirmasi dengan Bendahara dan warga.';
    $resolved = $this->workflow->resolveException($this->admin, $request, $handed, $reason, DemoCredentials::password());
    $again = $this->workflow->resolveException($this->admin, $request, $handed, $reason, DemoCredentials::password());
    expect($again->id)->toBe($resolved->id)->and(LedgerEntry::count())->toBe($handed ? 2 : 1)->and($resolved->status)->toBe($handed ? WithdrawalStatus::Paid : WithdrawalStatus::Waiting);
})->with([true, false]);
