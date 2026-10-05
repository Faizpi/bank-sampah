<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Officer\GroceryTasks;
use App\Livewire\Treasurer\WithdrawalPayments;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Database\Seeders\DeveloperUsersSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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
});

it('creates internal accounts with exactly one role but never a citizen account on behalf of another person', function (): void {
    $data = ['name' => 'Pengurus dummy baru', 'username' => 'pengurus.baru', 'password' => 'PasswordOnly#2026', 'password_confirmation' => 'PasswordOnly#2026', 'role' => 'bendahara'];
    $user = app(ManageUsers::class)->create($this->admin, $data);
    expect($user->role->value)->toBe('bendahara');
    expect(fn () => app(ManageUsers::class)->create($this->admin, [...$data, 'username' => 'warga.atasnama', 'role' => 'warga']))->toThrow(ValidationException::class);
});

it('does not let a stale citizen role create new service requests', function (string $domain): void {
    $stale = $this->warga;
    app(ChangeUserRole::class)->handle($this->admin, $stale, 'admin', 'Perubahan peran untuk uji akses.');
    $callback = match ($domain) {
        'withdrawal' => fn () => app(WithdrawalService::class)->request($stale, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid()),
        'grocery' => fn () => app(GroceryService::class)->request($stale, ['package_id' => GroceryPackage::firstOrFail()->id], (string) str()->uuid()),
        default => fn () => app(PickupService::class)->submit($stale, ['address' => 'Alamat uji', 'selected_date' => today()->toDateString()], [['waste_type_id' => $this->items[0]['waste_type_id']]], [], (string) str()->uuid()),
    };
    expect($callback)->toThrow(AuthorizationException::class);
})->with(['withdrawal', 'grocery', 'pickup']);

it('returns a committed deposit after the temporary idempotency row is removed', function (): void {
    $service = app(DepositService::class);
    $key = (string) str()->uuid();
    $deposit = $service->finalize($this->petugas, $service->createDraft($this->petugas, $this->warga), $key, $this->items);
    IdempotencyKey::query()->delete();
    $retry = $service->finalize($this->petugas, $deposit, $key, $this->items);
    expect($retry->id)->toBe($deposit->id)->and(LedgerEntry::count())->toBe(1);
});

it('freezes a cancellation after claim and preserves funds if physical delivery was already made', function (): void {
    $depositService = app(DepositService::class);
    $depositService->finalize($this->petugas, $depositService->createDraft($this->petugas, $this->warga), (string) str()->uuid(), $this->items);
    $request = app(WithdrawalService::class)->request($this->warga, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    $workflow = app(ClaimedServiceWorkflow::class);
    $workflow->claim($this->bendahara, $request);
    $pending = $workflow->cancel($this->warga, $request, 'Warga meminta batal saat tugas diproses.');
    expect($pending->balanceHold->isActive())->toBeTrue()->and($pending->status->value)->toBe('diproses_bendahara');
    expect(fn () => $workflow->complete($this->bendahara, $request, null, (string) str()->uuid()))->toThrow(ValidationException::class);
    $done = $workflow->resolveException($this->admin, $request, true, 'Uang dipastikan sudah diberikan sebelum permintaan batal.', DeveloperUsersSeeder::password(), now()->subMinute()->format('Y-m-d H:i:s'));
    expect($done->status->value)->toBe('sudah_dibayar')->and($done->physical_handed_over_at)->not->toBeNull()->and(LedgerEntry::count())->toBe(2);
});

it('rejects a stale officer from reading a deposit form outside the revoked area', function (): void {
    $this->petugas->staffProfile->serviceAreas()->delete();
    $this->actingAs($this->petugas)->get(route('officer.deposit-form', ['customerId' => $this->warga->id]))->assertNotFound();
});

it('refuses demo reset on a disposable test database or without confirmation', function (): void {
    $this->artisan('app:reset-demo-baseline')->assertExitCode(1);
    $this->artisan('app:reset-demo-baseline', ['--confirm-reset-demo' => true, '--dry-run' => true])->assertExitCode(1);
});

it('does not let claimant cancellation override an escalated Admin freeze', function (string $domain): void {
    $depositService = app(DepositService::class);
    $depositService->finalize($this->petugas, $depositService->createDraft($this->petugas, $this->warga), (string) str()->uuid(), $this->items);
    $request = $domain === 'cash'
        ? app(WithdrawalService::class)->request($this->warga, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid())
        : app(GroceryService::class)->request($this->warga, ['package_id' => GroceryPackage::firstOrFail()->id], (string) str()->uuid());
    $claimant = $domain === 'cash' ? $this->bendahara : $this->petugas;
    $workflow = app(ClaimedServiceWorkflow::class);
    $workflow->claim($claimant, $request);
    $workflow->cancel($this->warga, $request, 'Warga meminta pembatalan setelah klaim.');
    $workflow->freezeException($this->admin, $request, 'Fakta serah fisik diperselisihkan; jangan lepas hold.', DeveloperUsersSeeder::password());
    expect(fn () => $workflow->cancel($claimant, $request, 'Pelaksana mencoba konfirmasi pembatalan.'))->toThrow(ValidationException::class);
    expect($request->fresh()->balanceHold->isActive())->toBeTrue()->and(LedgerEntry::count())->toBe(1);
})->with(['cash', 'grocery']);

it('replays pre-completion UI state after the successful response is lost', function (string $domain): void {
    $depositService = app(DepositService::class);
    $depositService->finalize($this->petugas, $depositService->createDraft($this->petugas, $this->warga), (string) str()->uuid(), $this->items);
    $request = $domain === 'cash'
        ? app(WithdrawalService::class)->request($this->warga, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid())
        : app(GroceryService::class)->request($this->warga, ['package_id' => GroceryPackage::firstOrFail()->id], (string) str()->uuid());
    $this->actingAs($domain === 'cash' ? $this->bendahara : $this->petugas);
    $component = $domain === 'cash' ? new WithdrawalPayments : new GroceryTasks;
    $component->mount();
    $component->select($request->id);
    $component->claim();
    $preResponseState = clone $component;
    $component->complete();
    $originalReceipt = $component->receipt;
    Carbon::setTestNow(now()->addMinutes(2));
    try {
        $preResponseState->complete();
        expect($preResponseState->receipt)->toBe($originalReceipt)->and(LedgerEntry::count())->toBe(2);
    } finally {
        Carbon::setTestNow();
    }
})->with(['cash', 'grocery']);
