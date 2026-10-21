<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Seeding\HostingDemoGuard;
use App\Support\Seeding\MasterDataImporter;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;
use Throwable;

final class InstallHostingDemo extends Command
{
    protected $signature = 'app:install-hosting-demo
        {--database= : Nama database tepat, diketik ulang oleh operator}
        {--reset : Hapus seluruh tabel pada database dummy yang dikonfirmasi}
        {--backup-confirmed : Operator memastikan backup database dan berkas sudah disimpan}
        {--confirm= : Wajib INSTALL HOSTING DUMMY untuk mengubah data}
        {--dry-run : Validasi sumber dan tampilkan jumlah tanpa perubahan}';

    protected $description = 'Pasang data utama privat pada dummy hosting; dinonaktifkan secara default, bukan untuk data operasional.';

    public function handle(HostingDemoGuard $guard, MasterDataImporter $importer): int
    {
        $lock = null;
        try {
            $guard->validateTarget((string) $this->option('database'));
            $master = $guard->source();
            $this->info('Target: '.DB::connection()->getDatabaseName().' @ '.DB::connection()->getConfig('host'));
            foreach (['users', 'deposits', 'ledger_entries', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions'] as $table) {
                $this->line($table.': '.(Schema::hasTable($table) ? DB::table($table)->count() : 0).' baris saat ini');
            }
            $this->info('Sumber valid: '.count($master->tables['users']).' akun, '.count($master->tables['rt']).' RT, '.count($master->tables['waste_prices']).' harga.');
            if ($this->option('dry-run')) {
                $this->info('Pratinjau saja; database, maintenance, dan berkas tidak diubah.');

                return self::SUCCESS;
            }
            if (! $this->option('backup-confirmed') || $this->option('confirm') !== 'INSTALL HOSTING DUMMY') {
                throw new LogicException('Backup dan konfirmasi INSTALL HOSTING DUMMY wajib sebelum perubahan.');
            }
            if (! $this->option('reset')) {
                foreach (array_keys($master->tables) as $table) {
                    if (Schema::hasTable($table) && DB::table($table)->exists()) {
                        throw new LogicException('Seed tanpa reset hanya untuk database kosong. Data yang ada tidak diubah.');
                    }
                }
            }
            $lock = fopen(storage_path('framework/hosting-demo.lock'), 'c');
            if ($lock === false || ! flock($lock, LOCK_EX | LOCK_NB)) {
                throw new LogicException('Pemasangan dummy lain sedang berjalan.');
            }
            $guard->validateTarget((string) $this->option('database'));
            if ($this->call('down', ['--retry' => 60]) !== self::SUCCESS) {
                throw new LogicException('Maintenance gagal; database tidak dihapus.');
            }
            // Separate exit-code checks: migrate:fresh can mask an unsuccessful child seed command.
            if ($this->option('reset') && $this->call('db:wipe', ['--force' => true]) !== self::SUCCESS) {
                throw new LogicException('Penghapusan dummy gagal. Pulihkan backup sebelum membuka layanan.');
            }
            if ($this->call('migrate', ['--force' => true]) !== self::SUCCESS) {
                throw new LogicException('Migrasi gagal. Maintenance dipertahankan; jangan lanjutkan seed.');
            }
            $importer->install($master);
            foreach ($master->tables as $table => $rows) {
                if (DB::table($table)->count() !== count($rows)) {
                    throw new LogicException('Verifikasi jumlah data hasil seed gagal; periksa sebelum membuka layanan.');
                }
            }
            $this->info('Data utama empat peran terpasang, transaksi kosong. APP_KEY dan berkas lama tidak diubah/dihapus.');
            $this->warn('Maintenance tetap aktif. Selesaikan deployment, periksa hasil, lalu buka layanan. Matikan HOSTING_DEMO_INSTALL_ENABLED setelah selesai.');

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            // Do not echo SQL values or source account data into console output.
            $this->error($exception instanceof LogicException ? $exception->getMessage() : 'Pemasangan ditolak/gagal. Periksa sumber privat, skema, izin database dan log internal.');

            return self::FAILURE;
        } finally {
            if (is_resource($lock)) {
                flock($lock, LOCK_UN);
                fclose($lock);
            }
        }
    }
}
