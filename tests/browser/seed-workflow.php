<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\WorkflowFixture;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== getenv('DB_DATABASE') || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Resolved browser database is not the requested disposable loopback MySQL.');
}
Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => WorkflowFixture::class, '--force' => true]);
$users = [];
foreach (['warga', 'petugas', 'bendahara', 'admin'] as $role) {
    $users[$role] = User::where('username', $role)->firstOrFail();
    $users[$role]->forceFill(['password' => 'AuditOnly#2026'])->save();
}
$deposits = app(DepositService::class);
foreach (['AQUA-BOTOL', 'AQUA-GELAS'] as $code) {
    $type = WasteType::where('code', $code)->firstOrFail();
    $deposits->finalize($users['petugas'], $deposits->createDraft($users['petugas'], $users['warga']), (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']]);
}
$type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
$condition = $type->conditions()->firstOrFail();
$withdrawal = app(WithdrawalService::class)->request($users['warga'], ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
$grocery = app(GroceryService::class)->request($users['warga'], ['package_id' => GroceryPackage::firstOrFail()->id], (string) str()->uuid());
$pickup = app(PickupService::class)->submit($users['warga'], ['service_area_id' => $users['petugas']->staffProfile->service_area_id, 'selected_date' => today()->toDateString(), 'address' => $users['warga']->customerProfile->address], [['waste_type_id' => $type->id]], [], (string) str()->uuid());
$draft = $deposits->createDraft($users['petugas'], $users['warga']);
$deposits->replaceDraftItems($users['petugas'], $draft, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);
echo json_encode(['customerId' => $users['warga']->id, 'draftId' => $draft->id, 'typeId' => $type->id, 'conditionId' => $condition->id, 'withdrawalId' => $withdrawal->id, 'withdrawalNumber' => $withdrawal->request_number, 'groceryId' => $grocery->id, 'groceryNumber' => $grocery->request_number, 'pickupId' => $pickup->id, 'pickupNumber' => $pickup->request_number, 'address' => $users['warga']->customerProfile->address], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR).PHP_EOL;
