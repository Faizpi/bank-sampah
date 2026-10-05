<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
Artisan::call('migrate', ['--force' => true]);
if (User::query()->exists()) {
    throw new RuntimeException('Workflow fixtures require an empty disposable database.');
}
(new RolesAndPermissionsSeeder)->run();
$users = [];
foreach (['warga', 'petugas', 'bendahara', 'admin', 'superadmin'] as $role) {
    $user = User::factory()->create(['name' => 'Akun Uji '.ucfirst($role), 'username' => 'audit.'.$role, 'password' => 'AuditOnly#2026']);
    $user->roles()->attach(Role::where('name', $role)->firstOrFail());
    $users[$role] = $user;
}
$regions = app(ManageRegions::class);
$dusun = $regions->createDusun($users['admin'], 'AUDIT-DS', 'Dusun uji');
$rw = $regions->createRw($users['admin'], $dusun, 'AUDIT-RW', 'RW uji');
$rt = $regions->createRt($users['admin'], $rw, 'AUDIT-RT', 'RT uji');
$area = $regions->createServiceArea($users['admin'], 'Area uji', [$rt]);
$users['warga']->customerProfile()->create(['customer_number' => 'CST-00000001', 'rt_id' => $rt->id, 'address' => 'Alamat sintetis untuk uji antarmuka']);
foreach (['petugas', 'bendahara'] as $role) {
    StaffProfile::create(['user_id' => $users[$role]->id, 'staff_number' => 'STF-AUDIT-'.$users[$role]->id, 'service_area_id' => $area->id, 'active_from' => today()->subDay()]);
}
$account = app(LedgerService::class)->ensureAccount($users['warga']);
LedgerEntry::create(['entry_number' => 'LED-AUDIT-OPENING', 'ledger_account_id' => $account->id, 'direction' => 'masuk', 'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 150000, 'source_type' => User::class, 'source_id' => $users['warga']->id, 'source_key' => 'audit-opening-balance', 'effective_at' => now(), 'balance_after' => 150000]);
$category = WasteCategory::factory()->create(['is_active' => true]);
$unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
$condition = WasteCondition::factory()->create(['name' => 'Bersih', 'is_active' => true]);
$type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create(['name' => 'Botol plastik uji', 'is_active' => true]);
WasteMasterMutationGuard::run(fn () => $type->conditions()->sync([$condition->id]));
app(ManageWastePricing::class)->createPeriod($users['admin'], $type, $condition, 3000, today()->toImmutable(), null, (string) Str::uuid());
$withdrawal = app(WithdrawalService::class)->request($users['warga'], ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->addDay()->toDateString()], 'audit-browser-withdrawal-001');
$package = GroceryPackage::create(['code' => 'GRC-AUDIT', 'name' => 'Paket uji', 'contents' => 'Beras dan minyak untuk simulasi', 'value' => 25000, 'status' => 'aktif']);
$grocery = app(GroceryService::class)->request($users['warga'], ['package_id' => $package->id], 'audit-browser-grocery-001');
$pickup = app(PickupService::class)->submit($users['warga'], ['service_area_id' => $area->id, 'selected_date' => today()->addDay()->toDateString(), 'address' => 'Alamat sintetis untuk uji antarmuka'], [['waste_type_id' => $type->id, 'estimated_weight_kg' => '1.000']], [UploadedFile::fake()->image('request.jpg')], 'audit-browser-pickup-001');
$draft = app(DepositService::class)->createDraft($users['petugas'], $users['warga']);
app(DepositService::class)->replaceDraftItems($users['petugas'], $draft, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
echo json_encode(['customerId' => $users['warga']->id, 'draftId' => $draft->id, 'withdrawalId' => $withdrawal->id, 'withdrawalNumber' => $withdrawal->request_number, 'groceryId' => $grocery->id, 'pickupId' => $pickup->id, 'pickupNumber' => $pickup->request_number], JSON_PRETTY_PRINT), PHP_EOL;
