<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

final class ResetDemoBaseline extends Command
{
    protected $signature = 'app:reset-demo-baseline {--confirm-reset-demo : Hapus data dummy lokal secara sadar} {--dry-run : Tampilkan sasaran tanpa menghapus}';

    protected $description = 'Reset bersih data dummy empat role; hanya database lokal bank_sampah.';

    public function handle(Filesystem $files): int
    {
        $connection = config('database.connections.'.config('database.default'));
        if (! is_array($connection) || ! in_array(config('app.env'), ['local', 'testing'], true)
            || ($connection['driver'] ?? null) !== 'mysql'
            || ! in_array($connection['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || ($connection['database'] ?? null) !== 'bank_sampah'
            || ! $this->option('confirm-reset-demo')) {
            $this->error('Ditolak: hanya lingkungan local/testing, MySQL loopback, tepat database bank_sampah, dan --confirm-reset-demo.');

            return self::FAILURE;
        }
        $resolved = DB::connection()->getConfig();
        if (! in_array($resolved['host'] ?? null, ['127.0.0.1', 'localhost', '::1'], true)
            || ($resolved['database'] ?? null) !== 'bank_sampah'
            || DB::connection()->getDatabaseName() !== 'bank_sampah') {
            $this->error('Ditolak: koneksi database hasil resolusi berbeda dari sasaran dummy.');

            return self::FAILURE;
        }
        $base = realpath(base_path());
        $storage = realpath(storage_path('app'));
        $expected = realpath(base_path('storage/app'));
        if ($base === false || $storage === false || $expected === false || $storage !== $expected
            || ! str_starts_with(strtolower(str_replace('\\', '/', $storage)).'/', strtolower(str_replace('\\', '/', $base)).'/')) {
            $this->error('Ditolak: storage bukan folder dummy di workspace aplikasi.');

            return self::FAILURE;
        }
        $media = $storage.DIRECTORY_SEPARATOR.'media';
        if (file_exists($media) && (is_link($media) || realpath($media) !== $media)) {
            $this->error('Ditolak: root media berupa tautan atau path tidak sesuai.');

            return self::FAILURE;
        }
        $this->info('Database sasaran: bank_sampah pada '.$connection['host']);
        $this->info('Media dummy sasaran: '.$media);
        foreach (['users', 'deposits', 'ledger_entries', 'balance_holds', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions', 'media'] as $table) {
            $this->line($table.': '.(Schema::hasTable($table) ? DB::table($table)->count() : 0).' row');
        }
        if ($this->option('dry-run')) {
            $this->info('Dry run: tidak ada data atau media yang dihapus.');

            return self::SUCCESS;
        }
        if ($this->call('migrate:fresh', ['--seed' => true, '--force' => true]) !== self::SUCCESS) {
            $this->error('Reset gagal; media tidak dihapus. Periksa migrasi dan pulihkan backup bila diperlukan.');

            return self::FAILURE;
        }
        $roles = DB::table('users')->pluck('role')->sort()->values()->all();
        if ($roles !== ['admin', 'bendahara', 'petugas', 'warga']) {
            $this->error('Seed tidak sesuai empat role; media tidak dihapus.');

            return self::FAILURE;
        }
        if (is_dir($media) && ! $files->deleteDirectory($media)) {
            $this->error('Schema dan seed selesai, tetapi pembersihan media gagal.');

            return self::FAILURE;
        }
        $files->ensureDirectoryExists($media);
        $this->info('Reset selesai: empat akun dummy, area Petugas/Bendahara, master harga/paket; transaksi kosong.');

        return self::SUCCESS;
    }
}
