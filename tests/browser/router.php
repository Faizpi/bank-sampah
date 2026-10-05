<?php

declare(strict_types=1);
use Illuminate\Contracts\Http\Kernel;
use Illuminate\Foundation\Vite;
use Illuminate\Http\Request;

if (getenv('APP_ENV') !== 'testing') {
    http_response_code(404);
    exit;
}
$public = realpath(dirname(__DIR__, 2).'/public');
$file = realpath($public.'/'.ltrim((string) parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH), '/'));
if ($file !== false && str_starts_with($file, $public.DIRECTORY_SEPARATOR) && is_file($file) && pathinfo($file, PATHINFO_EXTENSION) !== 'php') {
    return false;
}
$app = require __DIR__.'/bootstrap.php';
$app->make(Kernel::class)->bootstrap();
config()->set('filesystems.disks.tmp-for-tests', ['driver' => 'local', 'root' => $app->storagePath('app/livewire-testing'), 'throw' => true]);
$app->make(Vite::class)->useHotFile($app->storagePath('unused-hot'));
$app->handleRequest(Request::capture());
