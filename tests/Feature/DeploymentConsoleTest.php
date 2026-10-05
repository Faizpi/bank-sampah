<?php

declare(strict_types=1);

it('runs database migration before clearing the database-backed cache during a release', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    preg_match("/'release'\s*=>\s*\[.*?'commands'\s*=>\s*\[(.*?)\],\s*\],/s", (string) $console, $matches);

    expect($matches)->toHaveKey(1)
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['optimize:clear', []]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->toBeLessThan(strpos($matches[1], "['optimize:clear', []]"));
});

it('provides a standalone ordinary migration action that preserves existing data', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    $start = strpos((string) $console, "'migrate' => [");
    $end = strpos((string) $console, "'release' => [");
    $block = $start === false || $end === false ? '' : substr((string) $console, $start, $end - $start);

    expect($block)->not->toBe('')
        ->and($console)->toContain('Jalankan migrasi biasa')
        ->and($console)->toContain('tanpa menghapus tabel atau data')
        ->and($block)->toContain("['migrate', ['--force' => true]]")
        ->and($block)->not->toContain('migrate:fresh')
        ->and($block)->not->toContain('db:seed');
});

it('provides one unambiguous confirmed demo reseed action', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    preg_match("/'reseed-demo-data'\s*=>\s*\[.*?'commands'\s*=>\s*\[(.*?)\],\s*\],/s", (string) $console, $matches);

    expect($matches)->toHaveKey(1)
        ->and($console)->not->toContain("'fresh-release'")
        ->and($console)->toContain("'confirmation' => 'RESET DEMO DATABASE'")
        ->and($matches[1])->toContain("['migrate:fresh', ['--force' => true]]")
        ->and(substr_count($matches[1], 'Database\\\\Seeders\\\\DatabaseSeeder'))->toBe(1)
        ->and($matches[1])->not->toContain('DeveloperUsersSeeder')
        ->and($matches[1])->not->toContain('LocalDataSeeder');
});

it('provides a one-click idempotent data seed action for temporary production testing', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("'seed-demo-data'")
        ->and($console)->toContain('Database\\\\Seeders\\\\LocalDataSeeder')
        ->and($console)->not->toContain("'seed' => [")
        ->and($console)->not->toContain('Seed admin awal')
        ->and($console)->not->toContain("'SEED DATA UJI'");
});

it('migrates before seeding temporary demo data', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse();

    preg_match("/'seed-demo-data'\s*=>\s*\[.*?'commands'\s*=>\s*\[(.*?)\],\s*\],/s", (string) $console, $matches);

    expect($matches)->toHaveKey(1)
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['db:seed', ['--class' => 'Database\\\\Seeders\\\\LocalDataSeeder', '--force' => true]]"))->not->toBeFalse()
        ->and(strpos($matches[1], "['migrate', ['--force' => true]]"))->toBeLessThan(strpos($matches[1], "['db:seed', ['--class' => 'Database\\\\Seeders\\\\LocalDataSeeder', '--force' => true]]"));
});

it('shows a bounded exception summary to the authenticated deployment operator', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain('deployConsoleFailureMessage')
        ->and($console)->toContain("preg_replace('/\\s*\\(SQL:.*\$/i'")
        ->and($console)->toContain('Periksa log aplikasi melalui aksi Lihat log aplikasi');
});

it('groups deployment actions with semantic group and risk metadata', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("'diagnostics' => 'Diagnostik read-only'")
        ->and($console)->toContain("'routine' => 'Deployment rutin'")
        ->and($console)->toContain("'maintenance' => 'Cache & maintenance'")
        ->and($console)->toContain("'data' => 'Data awal & demo'")
        ->and($console)->toContain("'destructive' => 'Tindakan destruktif'")
        ->and($console)->toContain("'group' => 'diagnostics'")
        ->and($console)->toContain("'risk' => 'dangerous'")
        ->and($console)->not->toContain('nth-child');
});

it('provides focused read-only diagnostics for hosted deployment failures', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("['operations:validate-deployment', []]")
        ->and($console)->toContain("['operations:smoke', []]")
        ->and($console)->toContain("'deployment-readiness'")
        ->and($console)->toContain("version_compare(PHP_VERSION, '8.3.0', '>=')")
        ->and($console)->toContain('bootstrap/cache')
        ->and($console)->toContain("realpath(\$projectPath.'/public/build')")
        ->and($console)->toContain("\$manifest['resources/js/app.js']['file']")
        ->and($console)->toContain("str_contains(\$assetContents, 'data-photo-picker-input')")
        ->and($console)->toContain("str_contains(\$assetContents, 'compressed-upload-v2')")
        ->and($console)->toContain("str_contains(\$assetContents, 'Mengompres foto')")
        ->and($console)->toContain('isDownForMaintenance')
        ->and($console)->toContain("config('session.driver')")
        ->and($console)->toContain('$kernel->bootstrap()')
        ->and($console)->toContain("config('session.table', 'sessions')")
        ->and($console)->toContain("config('session.connection')")
        ->and($console)->toContain("config('cache.stores.database.connection')")
        ->and($console)->toContain('DB::connection($sessionConnection)->getSchemaBuilder()->hasTable($sessionTable)')
        ->and($console)->toContain('DB::connection($cacheConnection)->getSchemaBuilder()->hasTable($cacheTable)')
        ->and($console)->toContain('catch (Throwable)')
        ->and($console)->toContain('latestDeployLogLines');
});

it('describes migration, readiness, and smoke diagnostics exactly', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain('Membaca daftar migration tanpa mengubah aplikasi.')
        ->and($console)->not->toContain('Membaca daftar migration dan memeriksa koneksi database/session')
        ->and($console)->toContain('Memeriksa PHP, session database, cache database, aset Vite, direktori runtime, dan maintenance')
        ->and($console)->toContain('Memeriksa koneksi database, health route, private storage, Vite manifest, config cache, dan scheduler')
        ->and($console)->toContain("'session-table' => false");
});

it('provides practical bounded maintenance actions without worker daemon controls', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("['cache:clear', []]")
        ->and($console)->toContain("['config:cache', []]")
        ->and($console)->toContain("['route:cache', []]")
        ->and($console)->toContain("['view:cache', []]")
        ->and($console)->toContain("['down', ['--retry' => 60]]")
        ->and($console)->toContain("['up', []]")
        ->and($console)->toContain("['schedule:list', []]")
        ->and($console)->toContain("['schedule:run', []]")
        ->and($console)->not->toContain("['queue:work'")
        ->and($console)->not->toContain("['queue:listen'")
        ->and($console)->not->toContain("['storage:link'");
});

it('accepts only literal server-side actions and contains no process execution primitives', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain('$actions[$selectedAction]')
        ->and($console)->not->toContain('name="command"')
        ->and($console)->not->toContain('name="arguments"')
        ->and($console)->not->toContain('name="path"');

    foreach (['shell_exec(', 'exec(', 'system(', 'passthru(', 'proc_open(', 'popen(', 'Symfony\\Component\\Process'] as $primitive) {
        expect($console)->not->toContain($primitive);
    }
});

it('shows confirmation controls only when the selected action is dangerous', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->not->toContain("'confirmation' => 'RESET DATABASE'")
        ->and($console)->toContain("'confirmation' => 'RESET DEMO DATABASE'")
        ->and($console)->toContain('data-confirmation')
        ->and($console)->toContain('confirmation.hidden = confirmationPhrase ===')
        ->and($console)->toContain('confirmationInput.required = confirmationPhrase !==');
});

it('keeps arbitrary Tinker disabled by default behind the existing deploy token gate', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain("deployConsoleEnvironment('DEPLOY_CONSOLE_TINKER_ENABLED') === 'true'")
        ->and($console)->toContain("'tinker' => [")
        ->and($console)->toContain("'confirmation' => 'RUN ARBITRARY TINKER CODE'")
        ->and($console)->toContain('if (! hash_equals($configuredToken, $submittedToken))')
        ->and(strpos((string) $console, 'if (! hash_equals($configuredToken, $submittedToken))'))->toBeLessThan(strpos((string) $console, '$selectedAction === \'tinker\''));
});

it('invokes confirmed bounded Tinker source through an Artisan option without repopulating it', function (): void {
    $console = file_get_contents(base_path('public/deploy.php'));

    expect($console)->not->toBeFalse()
        ->and($console)->toContain('DEPLOY_CONSOLE_MAX_TINKER_CODE_LENGTH')
        ->and($console)->toContain('mb_strlen($tinkerCode) > DEPLOY_CONSOLE_MAX_TINKER_CODE_LENGTH')
        ->and($console)->toContain("(string) (\$_POST['confirm_tinker'] ?? '')")
        ->and($console)->toContain("(string) (\$_POST['tinker_code'] ?? '')")
        ->and($console)->toContain("\$kernel->call('tinker', ['--execute' => \$tinkerCode])")
        ->and($console)->not->toContain('name="tinker_code" value=')
        ->and($console)->not->toContain('<?= escapeDeployConsole($tinkerCode) ?>');
});
