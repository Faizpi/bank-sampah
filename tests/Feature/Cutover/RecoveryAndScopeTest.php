<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeveloperUsersSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(DatabaseSeeder::class);
    $this->warga = User::where('username', 'warga')->firstOrFail();
    $this->petugas = User::where('username', 'petugas')->firstOrFail();
    $this->bendahara = User::where('username', 'bendahara')->firstOrFail();
    $this->admin = User::where('username', 'admin')->firstOrFail();
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $this->items = [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']];
    $this->pickup = app(PickupService::class)->submit($this->warga, ['address' => 'Alamat uji pickup', 'selected_date' => today()->toDateString(), 'service_area_id' => $this->petugas->staffProfile->service_area_id], [['waste_type_id' => $type->id]], [], (string) str()->uuid());
});

it('runs pickup without estimates or photos and finalizes a single deposit', function (): void {
    $service = app(PickupService::class);
    $request = $service->claim($this->petugas, $this->pickup);
    $request = $service->begin($this->petugas, $request);
    $request = $service->markPickedUp($this->petugas, $request);
    $key = (string) str()->uuid();
    $done = $service->complete($this->petugas, $request, $this->items, $key);
    $retry = $service->complete($this->petugas, $request, $this->items, $key);
    expect($done->status)->toBe(PickupStatus::Completed)->and($done->deposit->status)->toBe('final')->and($retry->deposit_id)->toBe($done->deposit_id)->and(LedgerEntry::count())->toBe(1);
});

it('denies pickup cancellation by another claimant or after physical collection', function (): void {
    $service = app(PickupService::class);
    $request = $service->claim($this->petugas, $this->pickup);
    $other = User::factory()->create(['role' => 'petugas']);
    StaffProfile::create(['user_id' => $other->id, 'staff_number' => 'OTHER-OFFICER', 'service_area_id' => $request->service_area_id]);
    expect(fn () => $service->cancel($other, $request, 'Bukan tugas saya sendiri.'))->toThrow(AuthorizationException::class);
    $request = $service->markPickedUp($this->petugas, $service->begin($this->petugas, $request));
    expect(fn () => $service->cancel($this->petugas, $request, 'Sampah sudah dibawa ke lokasi.'))->toThrow(ValidationException::class);
    expect(LedgerEntry::count())->toBe(0);
});

it('removes revoked pickup claims from the old officer queue', function (): void {
    $service = app(PickupService::class);
    $service->claim($this->petugas, $this->pickup);
    $this->petugas->staffProfile->serviceAreas()->delete();
    expect($service->visibleFor($this->petugas)->count())->toBe(0);
    expect(fn () => $service->begin($this->petugas, $this->pickup->fresh()))->toThrow(AuthorizationException::class);
});

it('lets Admin freeze a revoked cash claim and resolve confirmed facts without a second outflow', function (): void {
    $deposit = app(DepositService::class)->createDraft($this->petugas, $this->warga);
    app(DepositService::class)->finalize($this->petugas, $deposit, (string) str()->uuid(), $this->items);
    $request = app(WithdrawalService::class)->request($this->warga, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    $workflow = app(ClaimedServiceWorkflow::class);
    $workflow->claim($this->bendahara, $request);
    $this->bendahara->forceFill(['status' => 'nonaktif'])->save();
    $workflow->freezeException($this->admin, $request, 'Akses pelaksana dicabut saat pembayaran.', DeveloperUsersSeeder::password());
    $done = $workflow->resolveException($this->admin, $request, true, 'Uang dipastikan sudah diserahkan.', DeveloperUsersSeeder::password());
    expect($done->status->value)->toBe('sudah_dibayar')->and($done->payer_id)->toBe($this->bendahara->id)->and(LedgerEntry::count())->toBe(2);
});

it('recovers a collected pickup only through reasoned Admin exception reassignment', function (): void {
    $service = app(PickupService::class);
    $request = $service->markPickedUp($this->petugas, $service->begin($this->petugas, $service->claim($this->petugas, $this->pickup)));
    $this->petugas->forceFill(['status' => 'nonaktif'])->save();
    $replacement = User::factory()->create(['role' => 'petugas']);
    StaffProfile::create(['user_id' => $replacement->id, 'staff_number' => 'REPLACEMENT-OFFICER', 'service_area_id' => $request->service_area_id]);
    $service->freezeException($this->admin, $request, 'Petugas sebelumnya berhalangan melanjutkan.', DeveloperUsersSeeder::password());
    $request = $service->reassignException($this->admin, $request, $replacement, 'Sampah dipastikan diterima Petugas pengganti.', DeveloperUsersSeeder::password());
    $done = $service->complete($replacement, $request, $this->items, (string) str()->uuid());
    expect($done->status)->toBe(PickupStatus::Completed)->and(LedgerEntry::count())->toBe(1);
});

it('rejects noncanonical status strings at the database boundary', function (): void {
    expect(fn () => DB::table('users')->where('id', $this->admin->id)->update(['status' => 'AKTIF']))->toThrow(QueryException::class);
    expect(fn () => DB::table('pickup_requests')->where('id', $this->pickup->id)->update(['status' => 'MENUNGGU_PETUGAS']))->toThrow(QueryException::class);
});

it('does not allow Treasurer to request other report types through a direct service call', function (): void {
    expect(fn () => app(ReportQueryService::class)->records($this->bendahara, 'deposits', ['start' => '2026-10-01', 'end' => '2026-10-06']))->toThrow(AuthorizationException::class);
});

it('requires recent Admin reauthentication for an official adjustment', function (): void {
    expect(fn () => app(LedgerService::class)->adjust($this->admin, $this->warga, 10000, 'Penyesuaian resmi dengan alasan.', (string) str()->uuid()))->toThrow(AuthorizationException::class);
});
