<?php

declare(strict_types=1);

use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use App\Support\Auth\AdminReauthentication;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Fixtures\WorkflowFixture;

$app = require dirname(__DIR__).'/browser/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || ! preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', DB::connection()->getDatabaseName()) || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Concurrency tests require disposable MySQL.');
}

if (($argv[1] ?? '') === 'worker-admin') {
    DB::beginTransaction();
    User::where('role', 'admin')->get(); // Deliberately establish the older REPEATABLE READ snapshot.
    echo "SNAPSHOT\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        $admin = User::findOrFail((int) $argv[2]);
        app(ChangeUserRole::class)->handle($admin, $admin, 'petugas', 'Pergantian jabatan concurrent.');
        DB::commit();
        echo "UNSAFE\n";
    } catch (ValidationException) {
        DB::rollBack();
        echo "BLOCKED\n";
    }
    exit;
}
if (($argv[1] ?? '') === 'worker-claim') {
    echo "READY\n";
    fflush(STDOUT);
    fgets(STDIN);
    echo "ATTEMPT\n";
    fflush(STDOUT);
    try {
        app(ClaimedServiceWorkflow::class)->claim(User::findOrFail((int) $argv[2]), WithdrawalRequest::findOrFail((int) $argv[3]));
        echo "UNSAFE\n";
    } catch (ValidationException) {
        echo "BLOCKED\n";
    }
    exit;
}
if (($argv[1] ?? '') === 'worker-balance') {
    DB::beginTransaction();
    $account = LedgerAccount::findOrFail((int) $argv[2]);
    $oldBalance = $account->availableBalance();
    echo "SNAPSHOT\n";
    fflush(STDOUT);
    fgets(STDIN);
    $account = LedgerAccount::whereKey($account->id)->lockForUpdate()->firstOrFail();
    $newBalance = $account->currentAvailableBalance();
    DB::commit();
    echo $newBalance === $oldBalance + 5000 ? "CURRENT\n" : "UNSAFE\n";
    exit;
}
if (($argv[1] ?? '') === 'worker-revoked-area') {
    DB::beginTransaction();
    StaffServiceArea::where('staff_profile_user_id', (int) $argv[2])->get();
    echo "SNAPSHOT\n";
    fflush(STDOUT);
    fgets(STDIN);
    try {
        app(ClaimedServiceWorkflow::class)->complete(User::findOrFail((int) $argv[2]), WithdrawalRequest::findOrFail((int) $argv[3]), null, (string) str()->uuid());
        DB::commit();
        echo "UNSAFE\n";
    } catch (AuthorizationException) {
        DB::rollBack();
        echo "BLOCKED\n";
    }
    exit;
}

function worker(array $arguments): array
{
    $process = proc_open([PHP_BINARY, __FILE__, ...$arguments], [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes);
    if (! is_resource($process)) {
        throw new RuntimeException('Cannot start the independent MySQL worker.');
    }
    stream_set_timeout($pipes[1], 20);

    return [$process, $pipes];
}

function expectLine($stream, string $expected): void
{
    if (trim((string) fgets($stream)) !== $expected) {
        throw new RuntimeException('Concurrent worker did not produce '.$expected);
    }
}

function finishWorker($process, array $pipes): void
{
    fclose($pipes[0]);
    fclose($pipes[1]);
    $error = stream_get_contents($pipes[2]);
    fclose($pipes[2]);
    if (proc_close($process) !== 0) {
        throw new RuntimeException('Worker failed: '.$error);
    }
}

Artisan::call('migrate:fresh', ['--force' => true]);
Artisan::call('db:seed', ['--class' => WorkflowFixture::class, '--force' => true]);
$first = User::where('username', 'admin')->firstOrFail();
$second = User::factory()->create(['role' => UserRole::Admin]);
[$process, $pipes] = worker(['worker-admin', (string) $second->id]);
expectLine($pipes[1], 'SNAPSHOT');
app(ChangeUserRole::class)->handle($second, $first, 'petugas', 'Pergantian jabatan pertama.');
fwrite($pipes[0], "GO\n");
expectLine($pipes[1], 'BLOCKED');
finishWorker($process, $pipes);
if (User::where('role', 'admin')->where('status', 'aktif')->count() !== 1) {
    throw new RuntimeException('Last Admin invariant failed.');
}

$warga = User::where('username', 'warga')->firstOrFail();
$petugas = User::where('username', 'petugas')->firstOrFail();
$payer = User::where('username', 'bendahara')->firstOrFail();
$other = User::factory()->create(['role' => UserRole::Treasurer]);
StaffProfile::create(['user_id' => $other->id, 'staff_number' => 'CONCURRENT-PAYER', 'service_area_id' => $payer->staffProfile->service_area_id]);
$type = WasteType::where('code', 'AQUA-BOTOL')->firstOrFail();
$deposit = app(DepositService::class)->createDraft($petugas, $warga);
app(DepositService::class)->finalize($petugas, $deposit, (string) str()->uuid(), [['waste_type_id' => $type->id, 'condition_id' => $type->conditions()->firstOrFail()->id, 'weight_kg' => '50.000']]);
$request = app(WithdrawalService::class)->request($warga, ['amount' => 20000, 'pickup_location' => 'Loket concurrency', 'pickup_date' => today()->toDateString()], (string) str()->uuid());
[$process, $pipes] = worker(['worker-claim', (string) $other->id, (string) $request->id]);
expectLine($pipes[1], 'READY');
DB::beginTransaction();
app(ClaimedServiceWorkflow::class)->claim($payer, $request);
fwrite($pipes[0], "GO\n");
expectLine($pipes[1], 'ATTEMPT');
DB::commit();
expectLine($pipes[1], 'BLOCKED');
finishWorker($process, $pipes);
if ($request->fresh()->payer_id !== $payer->id) {
    throw new RuntimeException('Exclusive claimant invariant failed.');
}
[$process, $pipes] = worker(['worker-revoked-area', (string) $payer->id, (string) $request->id]);
expectLine($pipes[1], 'SNAPSHOT');
DB::table('staff_service_areas')->where('staff_profile_user_id', $payer->id)->delete();
fwrite($pipes[0], "GO\n");
expectLine($pipes[1], 'BLOCKED');
finishWorker($process, $pipes);

$account = $warga->ledgerAccount;
[$process, $pipes] = worker(['worker-balance', (string) $account->id]);
expectLine($pipes[1], 'SNAPSHOT');
app(AdminReauthentication::class)->confirm($second, 'password');
app(LedgerService::class)->adjust($second, $warga, 5000, 'Tambahan dana untuk uji snapshot terkini.', (string) str()->uuid());
fwrite($pipes[0], "GO\n");
expectLine($pipes[1], 'CURRENT');
finishWorker($process, $pipes);
echo json_encode(['last_admin_stale_snapshot' => 'passed', 'exclusive_claim_two_connections' => 'passed', 'revoked_area_old_snapshot' => 'passed', 'current_balance_old_snapshot' => 'passed', 'database' => DB::connection()->getDatabaseName()], JSON_THROW_ON_ERROR).PHP_EOL;
