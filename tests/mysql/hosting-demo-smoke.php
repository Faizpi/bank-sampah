<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use App\Support\Seeding\HostingDemoGuard;
use App\Support\Seeding\MasterData;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

$app = require dirname(__DIR__).'/browser/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
$database = DB::connection()->getDatabaseName();
if (DB::getDriverName() !== 'mysql' || preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/D', $database) !== 1
    || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Only an explicitly isolated loopback MySQL database is allowed.');
}
$key = config('app.key');
$sourcePath = config('app.master_seed_path');
$data = MasterData::read((string) $sourcePath);
$guard = app(HostingDemoGuard::class);
$assert = static function (bool $condition, string $message): void {
    if (! $condition) {
        throw new RuntimeException($message);
    }
};
$reject = static function (Closure $check) use ($assert): void {
    try {
        $check();
    } catch (LogicException) {
        return;
    }
    $assert(false, 'An unsafe target was accepted.');
};
config()->set(['operations.hosting_demo.enabled' => false, 'operations.hosting_demo.database' => $database]);
$reject(fn () => $guard->validateTarget($database));
config()->set('operations.hosting_demo.enabled', true);
$reject(fn () => $guard->validateTarget($database.'_wrong'));
config()->set('app.env', 'local');
$reject(fn () => $guard->validateTarget($database));
config()->set('app.env', 'testing');
$guard->validateTarget($database);

// Marker must survive every rejection, then disappear only after the confirmed reset.
Schema::create('hosting_install_marker', static fn ($table) => $table->string('value'));
DB::table('hosting_install_marker')->insert(['value' => 'old-dummy']);
$parameters = ['--database' => $database, '--reset' => true, '--backup-confirmed' => true, '--confirm' => 'INSTALL HOSTING DUMMY'];
try {
    $assert(Artisan::call('app:install-hosting-demo', ['--database' => $database, '--dry-run' => true]) === 0, 'Dry-run failed.');
    $assert(Schema::hasTable('hosting_install_marker') && ! $app->isDownForMaintenance(), 'Dry-run mutated state.');
    foreach ([['--backup-confirmed' => false], ['--confirm' => 'wrong'], ['--database' => $database.'_wrong']] as $invalid) {
        $assert(Artisan::call('app:install-hosting-demo', array_replace($parameters, $invalid)) !== 0, 'Invalid confirmation accepted.');
        $assert(Schema::hasTable('hosting_install_marker') && ! $app->isDownForMaintenance(), 'Rejected install mutated state.');
    }
    config()->set('app.master_seed_path', storage_path('app/missing.json'));
    $assert(Artisan::call('app:install-hosting-demo', $parameters) !== 0, 'Missing source accepted.');
    $assert(Schema::hasTable('hosting_install_marker') && ! $app->isDownForMaintenance(), 'Missing source wiped data.');
    config()->set('app.master_seed_path', $sourcePath);
    // Exercise the explicit hosting-production branch against the prevalidated disposable DB only.
    config()->set('app.env', 'production');
    $assert(Artisan::call('app:install-hosting-demo', $parameters) === 0, 'Confirmed installation failed.');
    $assert(! Schema::hasTable('hosting_install_marker'), 'Old dummy table remains.');
    foreach ($data->tables as $table => $rows) {
        $assert(DB::table($table)->count() === count($rows), 'Master row count mismatch.');
    }
    foreach ($data->tables['users'] as $row) {
        $assert(hash_equals($row['password'], (string) DB::table('users')->where('id', $row['id'])->value('password')), 'Source hash changed.');
    }
    foreach (CustomerProfile::all() as $profile) {
        $assert(QrToken::fromValue($profile->qr_token_encrypted)->hash() === $profile->qr_token_hash, 'Invalid QR encryption.');
    }
    foreach (['deposits', 'ledger_entries', 'balance_holds', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions'] as $table) {
        $assert(DB::table($table)->count() === 0, 'Historical transactions imported.');
    }
    $assert(DB::table('users')->whereNotIn('role', ['warga', 'petugas', 'bendahara', 'admin'])->count() === 0, 'Unexpected role.');
    $assert(config('app.key') === $key && $app->isDownForMaintenance(), 'APP_KEY or maintenance contract changed.');
    $assert(Artisan::call('app:install-hosting-demo', array_replace($parameters, ['--reset' => false])) !== 0, 'Repeat install overwrote records.');
    echo json_encode(['result' => 'passed', 'accounts' => count($data->tables['users']), 'checks' => ['target-guards', 'dry-run-no-mutations', 'confirmation-backup-required', 'source-before-wipe', 'old-dummy-removed', 'source-counts', 'password-hashes-preserved', 'four-roles', 'new-qr-valid', 'empty-transactions', 'key-preserved', 'maintenance-retained', 'repeat-install-refused']], JSON_THROW_ON_ERROR).PHP_EOL;
} finally {
    Artisan::call('up'); // Only this runner's isolated maintenance file.
}
