<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Support\Auth\AuthenticatedUserRedirector;
use App\Support\Auth\PhoneNumber;
use Database\Seeders\DeveloperUsersSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class LoginForm extends Component
{
    public string $phone = '';

    public string $password = '';

    /**
     * @var array<string, array{label: string, phone: string}>|null
     */
    public ?array $demoAccounts = null;

    public function mount(): void
    {
        // If user is already authenticated, redirect straight to their dashboard
        if (auth()->check()) {
            $this->redirect(app(AuthenticatedUserRedirector::class)->dashboardUrl(), navigate: true);

            return;
        }

        if (app()->environment('production')) {
            return;
        }

        $this->demoAccounts = [
            'warga' => [
                'label' => 'Warga',
                'phone' => DeveloperUsersSeeder::telephone('warga'),
            ],
            'petugas' => [
                'label' => 'Petugas',
                'phone' => DeveloperUsersSeeder::telephone('petugas'),
            ],
            'bendahara' => [
                'label' => 'Bendahara',
                'phone' => DeveloperUsersSeeder::telephone('bendahara'),
            ],
        ];
    }

    public function fillDemo(string $role): void
    {
        if (app()->environment('production') || $this->demoAccounts === null) {
            return;
        }

        if (! array_key_exists($role, $this->demoAccounts)) {
            return;
        }

        $this->phone = $this->demoAccounts[$role]['phone'];
        $this->password = DeveloperUsersSeeder::DEV_PASSWORD;
    }

    public function login(AuthenticateUser $authenticateUser): void
    {
        $this->phone = PhoneNumber::normalize($this->phone);

        try {
            $validated = $this->validate();
            $user = $authenticateUser->handle($validated['phone'], $validated['password'], request());
            $redirector = app(AuthenticatedUserRedirector::class);
            $intendedUrl = $redirector->authorizedIntendedUrl(app('redirect')->getIntendedUrl(), request(), $user);

            if ($intendedUrl !== null) {
                $this->redirect($intendedUrl, navigate: true);

                return;
            }

            $this->redirect($redirector->dashboardUrl($user), navigate: true);
        } catch (ValidationException $exception) {
            $this->reset('password');
            $this->dispatch('login-invalid');

            throw $exception;
        }
    }

    /** @return array<string, list<string>> */
    protected function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'regex:/^62[0-9]{8,16}$/'],
            'password' => ['required', 'string'],
        ];
    }

    public function render(): View
    {
        return view('livewire.auth.login-form');
    }
}
