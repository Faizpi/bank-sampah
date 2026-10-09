<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use App\Support\Auth\Username;
use App\Support\Seeding\MasterData;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use LogicException;

/** The only application seeder; private village data is never bundled with the repository. */
final class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (! in_array(config('app.env'), ['local', 'testing'], true)) {
            $this->bootstrapAdmin();

            return;
        }
        $connection = DB::connection();
        if ($connection->getDriverName() !== 'sqlite'
            && ! in_array($connection->getConfig('host'), ['127.0.0.1', 'localhost', '::1'], true)) {
            throw new LogicException('Data utama hanya boleh di-seed pada database lokal.');
        }
        $master = app()->bound(MasterData::class)
            ? app(MasterData::class)
            : MasterData::read((string) config('app.master_seed_path'));
        DB::transaction(function () use ($master): void {
            foreach ([...array_keys(MasterData::COLUMNS), 'deposits', 'ledger_entries', 'balance_holds', 'pickup_requests', 'withdrawal_requests', 'grocery_redemptions'] as $table) {
                if (DB::table($table)->exists()) {
                    throw new LogicException('Seed utama hanya untuk database kosong. Data yang ada tidak akan ditimpa; backup dan gunakan reset lokal yang eksplisit.');
                }
            }
            // Use canonical dependency order, not the order of JSON keys.
            foreach (array_keys(MasterData::COLUMNS) as $table) {
                if ($master->tables[$table] !== []) {
                    DB::table($table)->insert($master->tables[$table]);
                }
            }
            // Source QR ciphertext belongs to another APP_KEY and must never be reused.
            foreach (CustomerProfile::query()->get() as $profile) {
                $token = QrToken::generate();
                $profile->forceFill([
                    'qr_token_hash' => $token->hash(),
                    'qr_token_encrypted' => $token->value(),
                    'qr_rotated_at' => now(),
                ])->save();
            }
        });
        $this->command?->info('Data utama pengguna/wilayah/sampah/harga selesai; transaksi lama tidak diimpor.');
    }

    private function bootstrapAdmin(): void
    {
        $username = config('app.initial_admin_username');
        $password = config('app.initial_admin_password');
        if (! is_string($username) || ! Username::isValid($username) || ! is_string($password) || mb_strlen($password) < 12) {
            $this->command?->warn('Atur APP_INITIAL_ADMIN_USERNAME dan APP_INITIAL_ADMIN_PASSWORD (12+ karakter) untuk Admin awal.');

            return;
        }
        $existing = User::query()->where('username', $username)->first();
        if ($existing !== null) {
            if (! $existing->hasRole('admin') || $existing->status !== UserStatus::Active) {
                throw new LogicException('Bootstrap must not elevate or reactivate an existing account.');
            }

            return;
        }
        $admin = new User;
        $admin->forceFill([
            'username' => $username, 'email' => config('app.initial_admin_email'),
            'name' => 'Administrator Awal', 'password' => Hash::make($password),
            'status' => UserStatus::Active, 'role' => 'admin', 'verified_at' => now(),
            'terms_version' => (string) config('app.terms_version'), 'terms_accepted_at' => now(),
        ])->save();
    }
}
