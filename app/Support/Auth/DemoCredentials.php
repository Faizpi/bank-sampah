<?php

declare(strict_types=1);

namespace App\Support\Auth;

use App\Models\User;
use Dotenv\Dotenv;
use Illuminate\Support\Facades\Hash;

/** Only synthetic test/demo credentials, never a source for village seed data. */
final class DemoCredentials
{
    public const DEV_PASSWORD = 'Dev#Sindangheula2026';

    /** @var array<string, array{username: string, phone?: string, email?: string, staff: bool, customer: bool}> */
    private const ACCOUNTS = [
        'warga' => ['username' => 'warga', 'phone' => '6281234567801', 'email' => 'warga@sindangheula.dev', 'staff' => false, 'customer' => true],
        'petugas' => ['username' => 'petugas', 'phone' => '6281234567802', 'email' => 'petugas@sindangheula.dev', 'staff' => true, 'customer' => false],
        'bendahara' => ['username' => 'bendahara', 'phone' => '6281234567803', 'email' => 'bendahara@sindangheula.dev', 'staff' => true, 'customer' => false],
        'admin' => ['username' => 'admin', 'phone' => '6281234567804', 'email' => 'admin@sindangheula.dev', 'staff' => false, 'customer' => false],
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
        return array_map(static fn (array $account): string => $account['phone'], self::ACCOUNTS);
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

        if ($environmentValue !== false && $environmentValue !== '') {
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

    public static function available(string $role): bool
    {
        if (! config('app.demo_mode') || ! self::canSeedDemoData() || ! isset(self::ACCOUNTS[$role])) {
            return false;
        }
        $user = User::query()->where('username', self::username($role))->first();

        return $user !== null && $user->hasRole($role) && $user->status->value === 'aktif'
            && Hash::check(self::password(), $user->password);
    }
}
