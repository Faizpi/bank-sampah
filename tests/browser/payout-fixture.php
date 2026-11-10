<?php

declare(strict_types=1);

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'bank_sampah_test_ui_payouts_20261110'
    || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Payout browser fixtures require their dedicated loopback test database.');
}
if (($argv[1] ?? '') === 'seed') {
    if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0 || Artisan::call('db:seed', ['--class' => WorkflowFixture::class, '--force' => true]) !== 0) {
        throw new RuntimeException('Payout fixture migration or seeding failed.');
    }
    foreach (User::all() as $user) {
        $user->forceFill(['password' => 'BrowserOnly#2026'])->save();
    }
    PayoutHarness::credit(User::where('username', 'warga')->firstOrFail(), User::where('username', 'petugas')->firstOrFail());
    echo "Isolated payout fixture ready.\n";
} elseif (($argv[1] ?? '') === 'work') {
    PayoutHarness::configure();
    Http::fake(static function ($request) {
        $row = SandboxPayout::where('reference_id', $request['reference_id'])->firstOrFail();

        return Http::response(PayoutHarness::response($row));
    });
    echo 'Verified mock outcomes: '.app(PayoutWorker::class)->run().PHP_EOL;
} else {
    throw new RuntimeException('Choose seed or work. No real provider is called by this fixture.');
}
