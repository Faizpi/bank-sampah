<?php

declare(strict_types=1);

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\Seeding\MasterData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;

$app = require dirname(__DIR__).'/browser/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || ! preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', DB::connection()->getDatabaseName())
    || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Master-data smoke requires a disposable loopback MySQL database.');
}
$data = MasterData::read((string) config('app.master_seed_path'));
if (Artisan::call('migrate:fresh', ['--seed' => true, '--force' => true]) !== 0) {
    throw new RuntimeException('Master-data migration/seed failed.');
}
foreach ($data->tables as $table => $rows) {
    if (DB::table($table)->count() !== count($rows)) {
        throw new RuntimeException('Imported row count differs from the private source.');
    }
}
foreach ($data->tables['users'] as $row) {
    if (! hash_equals($row['password'], (string) DB::table('users')->where('id', $row['id'])->value('password'))) {
        throw new RuntimeException('A source password hash changed.');
    }
}
foreach (CustomerProfile::all() as $profile) {
    if (QrToken::fromValue($profile->qr_token_encrypted)->hash() !== $profile->qr_token_hash) {
        throw new RuntimeException('Generated customer QR is invalid.');
    }
}
$counts = array_map('count', $data->tables);
// Only the disposable database receives transactions. Main-bank_sampah is never a test target.
DB::beginTransaction();
try {
    $citizen = User::where('role', 'warga')->firstOrFail();
    $officer = User::where('role', 'petugas')->firstOrFail();
    $treasurer = User::where('role', 'bendahara')->firstOrFail();
    $type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
    $deposits = app(DepositService::class);
    $deposit = $deposits->finalize($officer, $deposits->createDraft($officer, $citizen), (string) str()->uuid(), [
        ['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000'],
    ]);
    if ((int) $deposit->total_value !== 75000) {
        throw new RuntimeException('Source price is not applied to actual weighing.');
    }
    $request = app(WithdrawalService::class)->request($citizen, ['amount' => 20000, 'pickup_location' => 'Loket uji', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
    $workflow = app(ClaimedServiceWorkflow::class);
    $claim = $workflow->claim($treasurer, $request);
    $paid = $workflow->complete($treasurer, $claim, null, (string) str()->uuid());
    $workflow->complete($treasurer, $paid, null, (string) str()->uuid());
    $account = LedgerAccount::where('user_id', $citizen->id)->firstOrFail();
    if ($account->currentAvailableBalance() !== 55000) {
        throw new RuntimeException('Cash payment or retry changed the balance incorrectly.');
    }
    foreach (User::where('role', 'admin')->get() as $admin) {
        if (app(PermissionChecker::class)->allows($admin, 'withdrawal.pay') || app(PermissionChecker::class)->allows($admin, 'deposit.finalize')) {
            throw new RuntimeException('An imported Admin can perform routine physical work.');
        }
    }
} finally {
    DB::rollBack();
}
echo json_encode(['result' => 'passed', 'counts' => $counts, 'checks' => ['source-counts', 'password-hashes-preserved', 'qr-valid', 'weighing-source-price', 'cash-claim-completion-retry', 'admin-role-boundaries'], 'test_transactions_rolled_back' => true], JSON_THROW_ON_ERROR).PHP_EOL;
