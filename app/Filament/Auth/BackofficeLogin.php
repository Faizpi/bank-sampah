<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use Database\Seeders\DeveloperUsersSeeder;
use Filament\Auth\Pages\Login;
use Illuminate\Contracts\Support\Htmlable;

/**
 * Branded back-office login for the `backoffice` panel.
 *
 * Keeps the exact Filament authentication flow (email + password) by extending
 * {@see Login} and overriding only the rendered view. No vendor file is edited.
 *
 * Demo quick-fill buttons (admin / superadmin) are rendered by the view only in
 * non-production environments; the account list is guarded here and stays empty
 * in production so nothing runtime leaks.
 */
final class BackofficeLogin extends Login
{
    protected string $view = 'filament.backoffice.auth.login';

    /**
     * Auto-fill the email/password form state for a demo role.
     *
     * No-op in production and for unknown roles so credentials never leak.
     */
    public function fillDemo(string $role): void
    {
        if (app()->environment('production')) {
            return;
        }

        if (! in_array($role, ['admin', 'superadmin'], true)) {
            return;
        }

        $this->form->fill([
            'email' => DeveloperUsersSeeder::email($role),
            'password' => DeveloperUsersSeeder::DEV_PASSWORD,
        ]);
    }

    public function hasLogo(): bool
    {
        return false;
    }

    public function getHeading(): string|Htmlable|null
    {
        return null;
    }

    public function getSubheading(): string|Htmlable|null
    {
        return null;
    }
}
