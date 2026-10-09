<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\Fixtures\WorkflowFixture;

$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
if (DB::getDriverName() !== 'mysql' || DB::connection()->getDatabaseName() !== getenv('DB_DATABASE')
    || ! in_array(DB::connection()->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
    throw new RuntimeException('Catalogue UI fixtures require the requested disposable loopback database.');
}
if (Artisan::call('migrate:fresh', ['--force' => true]) !== 0
    || Artisan::call('db:seed', ['--class' => WorkflowFixture::class, '--force' => true]) !== 0) {
    throw new RuntimeException('Catalogue UI fixture setup failed.');
}
$admin = User::where('role', 'admin')->firstOrFail();
$admin->forceFill(['password' => 'AuditOnly#2026'])->save();
$file = storage_path('app/catalogue-test-photo.jpg');
file_put_contents($file, UploadedFile::fake()->image('test.jpg', 800, 600)->getContent());
echo json_encode(['database' => DB::connection()->getDatabaseName(), 'upload_file' => $file, 'note' => 'Synthetic image for upload tests only; never village catalogue content.'], JSON_THROW_ON_ERROR).PHP_EOL;
