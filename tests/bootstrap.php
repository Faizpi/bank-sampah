<?php

declare(strict_types=1);

require dirname(__DIR__).'/vendor/autoload.php';

$connection = $_ENV['DB_CONNECTION'] ?? getenv('DB_CONNECTION');
$database = $_ENV['DB_DATABASE'] ?? getenv('DB_DATABASE');
$safeSqlite = $connection === 'sqlite' && $database === ':memory:';
$safeMysql = in_array($connection, ['mysql', 'mariadb'], true)
    && is_string($database)
    && preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/', $database) === 1;

if (! $safeSqlite && ! $safeMysql) {
    throw new RuntimeException('Tests require SQLite :memory: or a disposable bank_sampah_test database.');
}

// Never share maintenance files, uploads, sessions or cached configuration with the application.
$runtime = sys_get_temp_dir().'/bank-sampah-tests-'.bin2hex(random_bytes(8));
foreach (['', '/app/media', '/app/private', '/app/public', '/framework/cache/data', '/framework/sessions', '/framework/views', '/logs', '/bootstrap'] as $directory) {
    if (! mkdir($runtime.$directory, 0777, true) && ! is_dir($runtime.$directory)) {
        throw new RuntimeException('Cannot create the isolated test storage.');
    }
}

foreach ([
    'APP_ENV' => 'testing',
    'APP_KEY' => 'base64:'.base64_encode(random_bytes(32)),
    'LARAVEL_STORAGE_PATH' => $runtime,
    'APP_CONFIG_CACHE' => $runtime.'/bootstrap/config.php',
    'APP_ROUTES_CACHE' => $runtime.'/bootstrap/routes.php',
    'APP_SERVICES_CACHE' => $runtime.'/bootstrap/services.php',
    'APP_PACKAGES_CACHE' => $runtime.'/bootstrap/packages.php',
] as $name => $value) {
    putenv($name.'='.$value);
    $_ENV[$name] = $_SERVER[$name] = $value;
}

// TestCase loads ".env.testing" by name; give it a guaranteed-empty file in the
// isolated runtime so a missing project-level ".env.testing" never warns and the
// developer's real ".env" is never consulted by the test suite.
file_put_contents($runtime.'/.env.testing', '');
putenv('BANK_SAMPAH_TEST_ENV_DIR='.$runtime);
$_ENV['BANK_SAMPAH_TEST_ENV_DIR'] = $_SERVER['BANK_SAMPAH_TEST_ENV_DIR'] = $runtime;
