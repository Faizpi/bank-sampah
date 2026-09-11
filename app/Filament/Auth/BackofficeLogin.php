<?php

declare(strict_types=1);

namespace App\Filament\Auth;

use App\Filament\Auth\Responses\BackofficeLoginResponse;
use App\Models\User;
use App\Support\Auth\Username;
use Database\Seeders\DeveloperUsersSeeder;
use Filament\Auth\Http\Responses\Contracts\LoginResponse;
use Filament\Auth\Pages\Login;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Validation\ValidationException;
use SensitiveParameter;

/**
 * Branded back-office login for the `backoffice` panel.
 *
 * Uses username authentication (same as the public login) by overriding the
 * Filament login form and credential extraction.
 */
final class BackofficeLogin extends Login
{
    protected string $view = 'filament.backoffice.auth.login';

    public function mount(): void
    {
        if (Filament::auth()->check()) {
            $user = Filament::auth()->user();
            if ($user instanceof User && $user->canAccessPanel(Filament::getCurrentOrDefaultPanel())) {
                $intended = session()->pull('url.intended');
                if (is_string($intended) && str_starts_with($intended, url('/backoffice')) && ! str_contains($intended, '/backoffice/login')) {
                    $this->redirect($intended);

                    return;
                }

                $this->redirect(Filament::getUrl());

                return;
            }
        }

        $this->form->fill();
    }

    public function authenticate(): ?LoginResponse
    {
        $response = parent::authenticate();

        if ($response instanceof LoginResponse) {
            return new BackofficeLoginResponse;
        }

        return $response;
    }

    /**
     * Auto-fill the username/password form state for a demo role.
     *
     * No-op in production and for unknown roles so credentials never leak.
     */
    public function fillDemo(string $role): void
    {
        if (! config('app.demo_mode')) {
            return;
        }

        if (! in_array($role, ['admin', 'superadmin'], true)) {
            return;
        }

        $this->form->fill([
            'username' => DeveloperUsersSeeder::username($role),
            'password' => DeveloperUsersSeeder::password(),
        ]);
    }

    protected function getEmailFormComponent(): Component
    {
        return TextInput::make('username')
            ->label('Username')
            ->required()
            ->autocomplete('username')
            ->autofocus();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function getCredentialsFromFormData(#[SensitiveParameter] array $data): array
    {
        return [
            'username' => Username::normalize(is_string($data['username'] ?? null) ? $data['username'] : ''),
            'password' => $data['password'] ?? '',
        ];
    }

    protected function throwFailureValidationException(): never
    {
        throw ValidationException::withMessages([
            'data.username' => __('filament-panels::auth/pages/login.messages.failed'),
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
