<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffProfile;
use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Local-only credentials for the four fixed roles. */
final class DeveloperUsersSeeder extends Seeder
{
    public const DEV_PASSWORD = 'Dev#Sindangheula2026';

    /** @var array<string, array{username: string, phone?: string, email?: string, staff: bool, customer: bool}> */
    private const ACCOUNTS = [
        'warga' => ['username' => 'warga', 'phone' => '6281234567801', 'email' => 'warga@sindangheula.dev', 'staff' => false, 'customer' => true],
        'petugas' => ['username' => 'petugas', 'phone' => '6281234567802', 'email' => 'petugas@sindangheula.dev', 'staff' => true, 'customer' => false],
        'bendahara' => ['username' => 'bendahara', 'phone' => '6281234567803', 'email' => 'bendahara@sindangheula.dev', 'staff' => true, 'customer' => false],
        'admin' => ['username' => 'admin', 'phone' => '6281234567804', 'email' => 'admin@sindangheula.dev', 'staff' => false, 'customer' => false],
    ];

    /** @var array<string, string> */
    private const DISPLAY_NAMES = [
        'warga' => 'Siti Aminah',
        'petugas' => 'Dadan Hidayat',
        'bendahara' => 'Dewi Lestari',
        'admin' => 'Rina Marlina',
    ];

    /** @return array<string, string> role name => username */
    public static function usernames(): array
    {
        return array_map(static fn (array $account): string => $account['username'], self::ACCOUNTS);
    }

    public static function username(string $role): string
    {
        return self::ACCOUNTS[$role]['username'];
    }

    /** @return array<string, string> role name => phone */
    public static function telephones(): array
    {
        return array_map(static fn (array $account): string => $account['phone'] ?? '', self::ACCOUNTS);
    }

    public static function telephone(string $role): string
    {
        return self::ACCOUNTS[$role]['phone'] ?? '';
    }

    public static function email(string $role): string
    {
        return self::ACCOUNTS[$role]['email'] ?? '';
    }

    public static function password(): string
    {
        $configuredPassword = self::demoSetting('app.demo_password', 'APP_DEMO_PASSWORD');

        if (is_string($configuredPassword) && $configuredPassword !== '') {
            return $configuredPassword;
        }

        return self::DEV_PASSWORD;
    }

    public static function canSeedDemoData(): bool
    {
        return in_array(config('app.env'), ['local', 'testing'], true);
    }

    public static function requireDemoDataConfiguration(): void
    {
        if (self::canSeedDemoData()) {
            return;
        }

        throw new \LogicException('Data demo hanya boleh dibuat pada lingkungan local atau testing.');
    }

    private static function demoSetting(string $configKey, string $environmentKey): mixed
    {
        $dotenvValue = self::projectEnvironmentValue($environmentKey);

        if ($dotenvValue !== null && $dotenvValue !== '') {
            return $dotenvValue;
        }

        $environmentValue = $_ENV[$environmentKey]
            ?? $_SERVER[$environmentKey]
            ?? getenv($environmentKey);

        if ($environmentValue !== false && $environmentValue !== null && $environmentValue !== '') {
            return $environmentValue;
        }

        // deploy.php loads .env before it boots Laravel so that a recently
        // changed demo flag is usable even while an older config cache exists.
        return config($configKey);
    }

    private static function projectEnvironmentValue(string $environmentKey): ?string
    {
        $environmentFile = base_path('.env');

        if (! is_readable($environmentFile)) {
            return null;
        }

        try {
            $contents = file_get_contents($environmentFile);

            if (! is_string($contents)) {
                return null;
            }

            $values = Dotenv::parse($contents);
            $value = $values[$environmentKey] ?? null;

            return is_string($value) ? $value : null;
        } catch (\Throwable) {
            return null;
        }
    }

    public function run(): void
    {
        RegionMutationGuard::run(fn () => $this->seedAccounts());
    }

    private function seedAccounts(): void
    {
        self::requireDemoDataConfiguration();

        // All generated accounts intentionally share the configured demo
        // password. Hash it once so a web-based deployment on shared hosting
        // does not spend its whole request budget repeating bcrypt work.
        $passwordHash = Hash::make(self::password());

        foreach (self::ACCOUNTS as $roleName => $account) {
            $user = User::query()->updateOrCreate(
                ['username' => $account['username']],
                [
                    'name' => self::DISPLAY_NAMES[$roleName],
                    'phone' => $account['phone'] !== '' ? $account['phone'] : null,
                    'email' => $account['email'] !== '' ? $account['email'] : null,
                    'email_verified_at' => now(),
                    'password' => $passwordHash,
                    'status' => UserStatus::Active,
                    'role' => $roleName,
                    'verified_at' => now(),
                    'terms_version' => (string) config('app.terms_version'),
                    'terms_accepted_at' => now(),
                ],
            );

            $this->ensureProfiles($user, $roleName, $account['customer'], $account['staff']);

            $this->command?->info("Created dev account for role [{$roleName}]: {$account['email']} / phone {$account['phone']}");
        }
    }

    private function ensureProfiles(User $user, string $roleName, bool $customer, bool $staff): void
    {
        if ($customer) {
            CustomerProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'customer_number' => 'CST-00000001',
                    'rt_id' => $this->localRt()->id,
                    'address' => 'Kampung Sukamaju RT 01/RW 02, Sindangheula',
                    'joined_at' => now()->toDateString(),
                ],
            );

            // The demo identity must ship a working QR card. Seed the token when
            // the profile has none (fresh seed) or only carries an orphan hash
            // whose plaintext was never stored. Re-seeding never rotates a token
            // that is already complete, so existing demo QR stays valid.
            /** @var CustomerProfile $profile */
            $profile = CustomerProfile::query()->where('user_id', $user->id)->firstOrFail();
            if ($profile->qr_token_hash === null || $profile->qr_token_encrypted === null) {
                $token = QrToken::generate();
                $profile->forceFill([
                    'qr_token_hash' => $token->hash(),
                    'qr_token_encrypted' => $token->value(),
                    'qr_rotated_at' => now(),
                ])->save();
            }
        }

        if ($staff) {
            $profile = StaffProfile::query()->updateOrCreate(
                ['user_id' => $user->id],
                [
                    'staff_number' => $roleName === 'bendahara' ? 'STF-SH-002' : 'STF-SH-001',
                    'service_area_id' => $this->localServiceArea()->id,
                    'active_from' => now()->subYear()->toDateString(),
                    'active_to' => null,
                ],
            );
            $this->localServiceArea()->rts()->syncWithoutDetaching([$this->localRt()->id]);
            $profile->serviceAreas()->updateOrCreate(['service_area_id' => $this->localServiceArea()->id], ['active_from' => now()->subYear()->toDateString(), 'active_to' => null]);
        }
    }

    private function localServiceArea(): ServiceArea
    {
        return ServiceArea::query()->firstOrCreate(
            ['name' => 'Layanan Sindangheula Tengah'],
            ['is_active' => true],
        );
    }

    private function localRt(): Rt
    {
        $dusun = Dusun::query()->firstOrCreate(
            ['code' => 'DSN-SH-TENGAH'],
            ['name' => 'Dusun Sindangheula Tengah', 'is_active' => true],
        );
        $rw = Rw::query()->firstOrCreate(
            ['dusun_id' => $dusun->id, 'code' => 'DSN-SH-TENGAH-RW-02'],
            ['name' => 'RW 02 Sindangheula Tengah', 'is_active' => true],
        );

        return Rt::query()->firstOrCreate(
            ['rw_id' => $rw->id, 'code' => 'DSN-SH-TENGAH-RW-02-RT-01'],
            ['name' => 'RT 01 RW 02', 'is_active' => true],
        );
    }
}
