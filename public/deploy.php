<?php

declare(strict_types=1);

use Dotenv\Dotenv;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Facades\DB;

/*
 * Protected release console for shared hosting.
 *
 * The visual interface deliberately keeps the familiar deploy workspace, but
 * execution is limited to an allowlist. This file must never become a web
 * terminal, because it is served from the public document root.
 */

const DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH = 12000;

$projectPath = realpath(__DIR__.'/../bank-sampah');

// On normal Laravel installs deploy.php lives in public/. On shared hosting
// with a locked public_html, it lives in public_html beside bank-sampah/.
if ($projectPath === false || ! is_file($projectPath.'/artisan')) {
    $projectPath = realpath(__DIR__.'/..');
}

if ($projectPath === false || ! is_file($projectPath.'/artisan')) {
    http_response_code(500);
    exit('Project root could not be resolved.');
}

if (! is_file($projectPath.'/vendor/autoload.php')) {
    http_response_code(503);
    exit('Dependencies are not installed. Upload the production vendor directory first.');
}

chdir($projectPath);

require $projectPath.'/vendor/autoload.php';

// Read the deployment token even when Laravel config has already been cached.
Dotenv::createImmutable($projectPath)->safeLoad();

/** @var Application $app */
$app = require $projectPath.'/bootstrap/app.php';

function deployConsoleEnvironment(string $key): string
{
    $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

    return is_string($value) ? trim($value) : '';
}

function escapeDeployConsole(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function compactDeployOutput(string $output): string
{
    if (mb_strlen($output) <= DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH) {
        return $output;
    }

    return "[Output lama dipotong demi keamanan.]\n\n".mb_substr($output, -DEPLOY_CONSOLE_MAX_OUTPUT_LENGTH);
}

function deployConsoleFailureMessage(Throwable $exception): string
{
    $message = preg_replace('/\s+/', ' ', trim($exception->getMessage()));

    if (! is_string($message) || $message === '') {
        $message = 'Pesan kesalahan tidak tersedia.';
    }

    // Exception messages can contain SQL values. Keep the console useful for
    // a trusted operator without exposing a full query, path, stack trace, or
    // configuration value in a browser response.
    $message = preg_replace('/\s*\(Connection:.*$/i', '', $message) ?? $message;
    $message = preg_replace('/\s*\(SQL:.*$/i', '', $message) ?? $message;

    return 'Gagal: '.$exception::class."\n".mb_strimwidth($message, 0, 700, '…');
}

function deployFrontendAssetIsCurrent(string $projectPath): bool
{
    $buildPath = realpath($projectPath.'/public/build');
    if ($buildPath === false) {
        return false;
    }

    $manifestPath = $buildPath.'/manifest.json';
    if (! is_file($manifestPath) || ! is_readable($manifestPath)) {
        return false;
    }

    $manifestContents = file_get_contents($manifestPath);
    if (! is_string($manifestContents)) {
        return false;
    }

    try {
        $manifest = json_decode($manifestContents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return false;
    }

    $asset = is_array($manifest) ? ($manifest['resources/js/app.js']['file'] ?? null) : null;
    if (! is_string($asset) || ! str_starts_with($asset, 'assets/') || str_contains($asset, '..')) {
        return false;
    }

    $assetPath = realpath($buildPath.'/'.$asset);
    if ($assetPath === false
        || ! str_starts_with($assetPath, $buildPath.DIRECTORY_SEPARATOR)
        || ! is_file($assetPath)
        || ! is_readable($assetPath)) {
        return false;
    }

    $assetContents = file_get_contents($assetPath);

    return is_string($assetContents)
        && str_contains($assetContents, 'data-photo-picker-input')
        && str_contains($assetContents, 'compressed-upload-v2')
        && str_contains($assetContents, 'Mengompres foto');
}

function deployReadinessStatus(string $projectPath, Application $app): string
{
    /** @var Kernel $kernel */
    $kernel = $app->make(Kernel::class);
    $kernel->bootstrap();
    $requirements = [
        'PHP >= 8.3' => version_compare(PHP_VERSION, '8.3.0', '>='),
        'pdo_mysql extension' => extension_loaded('pdo_mysql'),
        'Vite app asset current' => deployFrontendAssetIsCurrent($projectPath),
        'storage writable' => is_writable($projectPath.'/storage'),
        'views writable' => is_writable($projectPath.'/storage/framework/views'),
        'cache path writable' => is_writable($projectPath.'/bootstrap/cache'),
        'maintenance disabled' => ! $app->isDownForMaintenance(),
        'database connection' => false,
        'session-table' => false,
        'cache-table' => false,
    ];

    try {
        DB::connection()->select('select 1');
        $requirements['database connection'] = true;
        $sessionTable = (string) config('session.table', 'sessions');
        $sessionConnection = config('session.connection');
        $sessionConnection = is_string($sessionConnection) && $sessionConnection !== '' ? $sessionConnection : null;
        $requirements['session-table'] = config('session.driver') !== 'database'
            || DB::connection($sessionConnection)->getSchemaBuilder()->hasTable($sessionTable);
        $cacheTable = (string) config('cache.stores.database.table', 'cache');
        $cacheConnection = config('cache.stores.database.connection');
        $cacheConnection = is_string($cacheConnection) && $cacheConnection !== '' ? $cacheConnection : null;
        $requirements['cache-table'] = config('cache.default') !== 'database'
            || DB::connection($cacheConnection)->getSchemaBuilder()->hasTable($cacheTable);
    } catch (Throwable) {
        // Friendly failed statuses below are sufficient; never expose connection details.
    }

    $lines = [];
    foreach ($requirements as $label => $passes) {
        $lines[] = sprintf('[%s] %s', $passes ? 'OK' : 'FAIL', $label);
    }

    return implode("\n", $lines);
}

function latestDeployLogLines(string $logFile, int $lineLimit = 200): string
{
    if (! is_file($logFile) || ! is_readable($logFile)) {
        return 'Log aplikasi belum tersedia atau tidak dapat dibaca.';
    }

    $fileSize = filesize($logFile);

    if ($fileSize === false || $fileSize === 0) {
        return '(Log aplikasi kosong.)';
    }

    $bytesToRead = min($fileSize, 256 * 1024);
    $handle = fopen($logFile, 'rb');

    if ($handle === false) {
        return 'Log aplikasi tidak dapat dibuka.';
    }

    try {
        fseek($handle, -$bytesToRead, SEEK_END);
        $content = stream_get_contents($handle);
    } finally {
        fclose($handle);
    }

    if (! is_string($content)) {
        return 'Log aplikasi tidak dapat dibaca.';
    }

    $lines = preg_split('/\r\n|\r|\n/', $content) ?: [];

    if ($fileSize > $bytesToRead) {
        array_shift($lines);
    }

    $latestLines = array_slice($lines, -$lineLimit);

    return implode("\n", $latestLines) ?: '(Log aplikasi kosong.)';
}

$configuredToken = deployConsoleEnvironment('DEPLOY_CONSOLE_TOKEN');

if ($configuredToken === '') {
    http_response_code(503);
    exit('Deploy console is disabled. Set DEPLOY_CONSOLE_TOKEN in the private .env file to enable it.');
}

$allowedIps = array_values(array_filter(array_map(
    static fn (string $ip): string => trim($ip),
    explode(',', deployConsoleEnvironment('DEPLOY_CONSOLE_ALLOWED_IPS')),
)));

if ($allowedIps !== [] && ! in_array((string) ($_SERVER['REMOTE_ADDR'] ?? ''), $allowedIps, true)) {
    http_response_code(403);
    exit('This IP address is not authorised to use the deploy console.');
}

header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
$cspNonce = base64_encode(random_bytes(18));
header("Content-Security-Policy: default-src 'none'; style-src 'unsafe-inline' https://fonts.googleapis.com; script-src 'nonce-{$cspNonce}'; font-src https://fonts.gstatic.com; form-action 'self'; base-uri 'none'; frame-ancestors 'none'");

$actionGroups = [
    'diagnostics' => 'Diagnostik read-only',
    'routine' => 'Deployment rutin',
    'maintenance' => 'Cache & maintenance',
    'data' => 'Data awal & demo',
    'destructive' => 'Tindakan destruktif',
];

$actions = [
    'deployment-readiness' => [
        'label' => 'Periksa kesiapan runtime',
        'description' => 'Memeriksa PHP, session database, cache database, aset Vite, direktori runtime, dan maintenance tanpa mengubah aplikasi.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [],
    ],
    'validate-deployment' => [
        'label' => 'Validasi deployment',
        'description' => 'Menjalankan validasi topologi deployment tanpa akses jaringan.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [
            ['operations:validate-deployment', []],
        ],
    ],
    'smoke' => [
        'label' => 'Jalankan smoke check',
        'description' => 'Memeriksa koneksi database, health route, private storage, Vite manifest, config cache, dan scheduler tanpa mengubah data.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [
            ['operations:smoke', []],
        ],
    ],
    'status' => [
        'label' => 'Periksa status migrasi',
        'description' => 'Membaca daftar migration tanpa mengubah aplikasi.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [
            ['migrate:status', []],
        ],
    ],
    'migrate' => [
        'label' => 'Jalankan migrasi biasa',
        'description' => 'Menerapkan migration yang tertunda tanpa menghapus tabel atau data yang sudah ada.',
        'group' => 'routine',
        'risk' => 'routine',
        'commands' => [
            ['migrate', ['--force' => true]],
        ],
    ],
    'release' => [
        'label' => 'Selesaikan deployment',
        'description' => 'Menerapkan migration yang tertunda lalu membangun ulang seluruh cache produksi.',
        'group' => 'routine',
        'risk' => 'routine',
        'commands' => [
            ['migrate', ['--force' => true]],
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'reseed-demo-data' => [
        'label' => 'Reset + isi ulang data demo',
        'description' => 'Menghapus seluruh tabel, menjalankan migration dan seluruh seeder demo, lalu membangun ulang cache. Seluruh data saat ini akan hilang.',
        'group' => 'destructive',
        'risk' => 'dangerous',
        'confirmation' => 'RESET DEMO DATABASE',
        'commands' => [
            ['migrate:fresh', ['--force' => true]],
            ['db:seed', ['--class' => 'Database\\Seeders\\DatabaseSeeder', '--force' => true]],
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'seed-demo-data' => [
        'label' => 'Isi data uji lengkap',
        'description' => 'Membuat akun demo, wilayah, harga sampah, transaksi, saldo, pencairan, sembako, pengumuman, dan statistik. Hanya aktif bila APP_DEMO_MODE=true.',
        'group' => 'data',
        'risk' => 'routine',
        'commands' => [
            ['migrate', ['--force' => true]],
            ['db:seed', ['--class' => 'Database\\Seeders\\DeveloperUsersSeeder', '--force' => true]],
            ['db:seed', ['--class' => 'Database\\Seeders\\LocalDataSeeder', '--force' => true]],
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'clear-cache' => [
        'label' => 'Kosongkan cache aplikasi',
        'description' => 'Menghapus isi cache store database tanpa mengubah config, route, atau view cache.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['cache:clear', []],
        ],
    ],
    'cache-config' => [
        'label' => 'Cache konfigurasi',
        'description' => 'Membangun ulang config cache saja.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['config:cache', []],
        ],
    ],
    'cache-routes' => [
        'label' => 'Cache route',
        'description' => 'Membangun ulang route cache saja.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['route:cache', []],
        ],
    ],
    'cache-views' => [
        'label' => 'Cache view',
        'description' => 'Membangun ulang compiled view cache saja.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['view:cache', []],
        ],
    ],
    'maintenance-down' => [
        'label' => 'Aktifkan maintenance',
        'description' => 'Menutup aplikasi sementara dan meminta klien mencoba lagi setelah 60 detik.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['down', ['--retry' => 60]],
        ],
    ],
    'maintenance-up' => [
        'label' => 'Nonaktifkan maintenance',
        'description' => 'Membuka aplikasi kembali dari mode maintenance.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['up', []],
        ],
    ],
    'schedule-list' => [
        'label' => 'Lihat jadwal',
        'description' => 'Menampilkan daftar task scheduler tanpa menjalankannya.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [
            ['schedule:list', []],
        ],
    ],
    'schedule-run' => [
        'label' => 'Jalankan scheduler sekali',
        'description' => 'Menjalankan task scheduler yang sedang jatuh tempo satu kali lalu berhenti.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['schedule:run', []],
        ],
    ],
    'rebuild-cache' => [
        'label' => 'Bangun ulang cache',
        'description' => 'Gunakan setelah perubahan konfigurasi, route, atau view tanpa migration database.',
        'group' => 'maintenance',
        'risk' => 'routine',
        'commands' => [
            ['optimize:clear', []],
            ['config:cache', []],
            ['route:cache', []],
            ['view:cache', []],
        ],
    ],
    'view-logs' => [
        'label' => 'Lihat log aplikasi',
        'description' => 'Membaca 200 baris terakhir log aplikasi secara terbatas tanpa menghapus atau mengubahnya.',
        'group' => 'diagnostics',
        'risk' => 'read-only',
        'commands' => [],
    ],
];

$results = [];
$message = null;
$selectedAction = (string) ($_POST['action'] ?? 'status');

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    $submittedToken = (string) ($_POST['token'] ?? '');

    if (! hash_equals($configuredToken, $submittedToken)) {
        http_response_code(403);
        $message = ['error', 'Token deploy tidak valid.'];
    } elseif (! isset($actions[$selectedAction])) {
        http_response_code(422);
        $message = ['error', 'Aksi deployment tidak dikenal.'];
    } elseif (isset($actions[$selectedAction]['confirmation']) && ! hash_equals(
        $actions[$selectedAction]['confirmation'],
        (string) ($_POST['confirm_reset'] ?? ''),
    )) {
        http_response_code(422);
        $message = ['error', 'Konfirmasi tindakan berbahaya belum tepat.'];
    } elseif ($selectedAction === 'deployment-readiness') {
        try {
            $output = deployReadinessStatus($projectPath, $app);
            $success = ! str_contains($output, '[FAIL]');
        } catch (Throwable) {
            $output = '[FAIL] Pemeriksaan kesiapan tidak dapat diselesaikan. Periksa log aplikasi.';
            $success = false;
        }
        $results[] = [
            'command' => 'deployment-readiness',
            'success' => $success,
            'output' => $output,
            'duration' => 0.0,
        ];
        $message = $success
            ? ['success', 'Pemeriksaan kesiapan deployment lulus.']
            : ['error', 'Ada pemeriksaan kesiapan yang gagal.'];
    } elseif ($selectedAction === 'view-logs') {
        $results[] = [
            'command' => 'storage/logs/laravel.log',
            'success' => true,
            'output' => latestDeployLogLines($projectPath.'/storage/logs/laravel.log'),
            'duration' => 0.0,
        ];
        $message = ['success', 'Log aplikasi terbaru berhasil dimuat.'];
    } else {
        /** @var Kernel $kernel */
        $kernel = $app->make(Kernel::class);

        foreach ($actions[$selectedAction]['commands'] as [$command, $parameters]) {
            $startedAt = microtime(true);

            try {
                $exitCode = $kernel->call($command, $parameters);
                $output = $kernel->output();
                $results[] = [
                    'command' => $command,
                    'success' => $exitCode === 0,
                    'output' => $output === '' ? '(Tidak ada output.)' : $output,
                    'duration' => microtime(true) - $startedAt,
                ];

                if ($exitCode !== 0) {
                    break;
                }
            } catch (Throwable $exception) {
                report($exception);
                $results[] = [
                    'command' => $command,
                    'success' => false,
                    'output' => deployConsoleFailureMessage($exception)."\n\nPeriksa log aplikasi melalui aksi Lihat log aplikasi untuk detail internal.",
                    'duration' => microtime(true) - $startedAt,
                ];
                break;
            }
        }

        $hasFailure = array_filter($results, static fn (array $result): bool => ! $result['success']) !== [];
        $message = $hasFailure
            ? ['error', 'Deployment berhenti karena ada command yang gagal.']
            : ['success', 'Aksi deployment selesai dijalankan.'];
    }
}
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Deploy Console | Bank Sampah Sindangheula</title>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&display=swap');
        :root { --canvas:#f5f5f4; --surface:#fff; --soft:#fafaf9; --ink:#202124; --muted:#6f737b; --line:#dededb; --lime:#daf39f; --blue:#dceff7; --lilac:#ebd3ff; --yellow:#ffdeb0; --danger:#b42318; --danger-soft:#fee4e2; --success:#237a45; --success-soft:#dcf3e5; font-family:Manrope,ui-sans-serif,system-ui,sans-serif; color:var(--ink); background:var(--canvas); }
        * { box-sizing:border-box; } body { max-width:980px; margin:0 auto; padding:28px; line-height:1.5; } main { background:var(--surface); border:1px solid var(--line); border-radius:16px; padding:26px; } h1 { margin:0; font-size:28px; letter-spacing:-.035em; } h1::before { content:"HE"; display:inline-grid; place-items:center; width:38px; height:38px; margin-right:10px; border-radius:11px; background:var(--lilac); font-size:12px; vertical-align:4px; } p { margin:8px 0 0; } .muted, small { color:var(--muted); font-size:12px; } .notice, .result { margin-top:18px; padding:16px; border-radius:12px; } .success { color:#135b31; background:var(--success-soft); } .error { color:var(--danger); background:var(--danger-soft); } form { margin-top:20px; padding:18px; border:1px solid var(--line); border-radius:13px; background:var(--soft); } label { font-weight:800; font-size:13px; } input, button { font:inherit; } input[type=password], input[type=text] { width:100%; min-height:43px; margin-top:7px; padding:9px 11px; border:1px solid #aab8a4; border-radius:8px; background:#fff; } .action-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(210px,1fr)); gap:10px; margin-top:14px; } .action-card { display:flex; gap:10px; min-height:92px; padding:13px; border:1px solid var(--line); border-radius:10px; background:#fff; cursor:pointer; } .action-group { margin:18px 0 0; padding:0; border:0; } .action-group legend { padding:0; font-size:14px; font-weight:800; } .action-card[data-risk="read-only"] { background:var(--blue); } .action-card[data-risk="routine"] { background:var(--lime); } .action-card[data-risk="dangerous"] { background:var(--danger-soft); border-color:#f4b8b3; } .action-card input { width:16px; height:16px; margin:3px 0 0; accent-color:var(--ink); flex:0 0 auto; } .action-card strong { display:block; font-size:12px; } .action-card span { display:block; margin-top:4px; font-size:11px; color:#4d5056; font-weight:500; } .confirmation { margin-top:14px; } button { width:100%; min-height:44px; margin-top:18px; border:0; border-radius:8px; color:#fff; background:#24663d; font-weight:800; cursor:pointer; } button:hover { background:#174d2d; } pre { overflow:auto; margin:10px 0 0; padding:15px; color:#d8eadb; background:#122217; border-radius:9px; white-space:pre-wrap; word-break:break-word; font:11px/1.65 Consolas,"Liberation Mono",monospace; } .result small { display:block; margin-top:3px; } .result strong { font-size:13px; } @media (max-width:640px) { body { padding:16px; } main { padding:18px; } h1 { font-size:23px; } .action-grid { grid-template-columns:1fr; } }
    </style>
</head>
<body>
<main>
    <h1>Deploy workspace</h1>
    <p>Console terbatas untuk <strong>Bank Sampah Sindangheula</strong>. Pilih satu aksi yang sesuai; log tetap tersedia untuk dibaca, tetapi tidak bisa dihapus dari sini.</p>
    <p class="muted">Project: <?= escapeDeployConsole($projectPath) ?></p>

    <?php if ($message !== null) { ?>
        <div class="notice <?= escapeDeployConsole($message[0]) ?>"><?= escapeDeployConsole($message[1]) ?></div>
    <?php } ?>

    <form method="post" autocomplete="off">
        <label for="token">Token deploy</label>
        <input id="token" name="token" type="password" required autocomplete="current-password">

        <div style="margin-top:18px">
            <label>Pilih aksi</label>
            <?php foreach ($actionGroups as $group => $groupLabel) { ?>
                <fieldset class="action-group" data-group="<?= escapeDeployConsole($group) ?>">
                    <legend><?= escapeDeployConsole($groupLabel) ?></legend>
                    <div class="action-grid">
                        <?php foreach ($actions as $key => $action) { ?>
                            <?php if ($action['group'] !== $group) {
                                continue;
                            } ?>
                            <label class="action-card" data-risk="<?= escapeDeployConsole($action['risk']) ?>">
                                <input type="radio" name="action" value="<?= escapeDeployConsole($key) ?>" data-confirmation="<?= escapeDeployConsole($action['confirmation'] ?? '') ?>" <?= $selectedAction === $key ? 'checked' : '' ?> required>
                                <span><strong><?= escapeDeployConsole($action['label']) ?></strong><span><?= escapeDeployConsole($action['description']) ?></span></span>
                            </label>
                        <?php } ?>
                    </div>
                </fieldset>
            <?php } ?>
        </div>

        <div class="confirmation" hidden>
            <label for="confirm_reset">Ketik <strong data-confirmation-phrase></strong> untuk melanjutkan</label>
            <input id="confirm_reset" name="confirm_reset" type="text" autocomplete="off">
            <small>Tindakan ini dapat menghapus atau menambahkan data. Pastikan database yang dipilih benar.</small>
        </div>

        <button type="submit">Jalankan aksi</button>
    </form>

    <script nonce="<?= escapeDeployConsole($cspNonce) ?>">
        const actionInputs = document.querySelectorAll('input[name="action"]');
        const confirmation = document.querySelector('.confirmation');
        const confirmationInput = document.querySelector('#confirm_reset');
        const confirmationLabel = document.querySelector('[data-confirmation-phrase]');
        const updateConfirmation = () => {
            const selected = document.querySelector('input[name="action"]:checked');
            const confirmationPhrase = selected?.dataset.confirmation ?? '';
            confirmation.hidden = confirmationPhrase === '';
            confirmationInput.required = confirmationPhrase !== '';
            confirmationInput.value = '';
            confirmationLabel.textContent = confirmationPhrase;
        };
        actionInputs.forEach((input) => input.addEventListener('change', updateConfirmation));
        updateConfirmation();
    </script>

    <?php foreach ($results as $result) { ?>
        <section class="result <?= $result['success'] ? 'success' : 'error' ?>">
            <strong><?= escapeDeployConsole($result['command']) ?> — <?= $result['success'] ? 'berhasil' : 'gagal' ?></strong>
            <small><?= number_format($result['duration'], 2) ?> detik</small>
            <pre><?= escapeDeployConsole(compactDeployOutput($result['output'])) ?></pre>
        </section>
    <?php } ?>
</main>
</body>
</html>
