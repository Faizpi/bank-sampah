<?php

declare(strict_types=1);

require dirname(__DIR__, 2).'/vendor/autoload.php';

$database = getenv('DB_DATABASE');
if (getenv('APP_ENV') !== 'testing' || ! is_string($database) || preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', $database) !== 1 || strlen((string) getenv('E2E_ENVIRONMENT_TOKEN')) < 32) {
    throw new RuntimeException('Browser tests require an explicitly named disposable database and test token.');
}
$runtime = sys_get_temp_dir().'/'.$database;
foreach (['/app/media', '/app/private', '/framework/cache/data', '/framework/sessions', '/framework/views', '/logs', '/bootstrap'] as $directory) {
    if (! is_dir($runtime.$directory)) {
        mkdir($runtime.$directory, 0777, true);
    }
}
foreach (['CONFIG', 'ROUTES', 'SERVICES', 'PACKAGES'] as $cache) {
    putenv('APP_'.$cache.'_CACHE='.$runtime.'/bootstrap/'.strtolower($cache).'.php');
    $_ENV['APP_'.$cache.'_CACHE'] = $_SERVER['APP_'.$cache.'_CACHE'] = $runtime.'/bootstrap/'.strtolower($cache).'.php';
}
$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->addAbsoluteCachePathPrefix(substr(sys_get_temp_dir(), 0, 3));
$app->loadEnvironmentFrom('.env.browser-test');
$app->useStoragePath($runtime);

return $app;
