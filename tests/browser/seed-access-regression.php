<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\WorkflowFixture;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== 'bank_sampah_test_ui_20261021'
    || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('This regression fixture only accepts its dedicated loopback test database.');
}
if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0
    || Artisan::call('db:seed', ['--class' => WorkflowFixture::class, '--force' => true]) !== 0) {
    throw new RuntimeException('Isolated fixture setup failed.');
}
foreach (User::all() as $user) {
    $user->forceFill(['password' => 'BrowserOnly#2026'])->save();
}
echo 'Dedicated UI fixture ready.'.PHP_EOL;
