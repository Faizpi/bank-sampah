<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\AuthenticateUser;
use App\Support\Auth\AuthenticatedUserRedirector;
use App\Support\Auth\Username;
use Database\Seeders\DeveloperUsersSeeder;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class LoginForm extends Component
{
    public string $username = '';

    public string $password = '';

    /**
     * @var array<string, array{label: string, username: string}>|null
     */
    public ?array $demoAccounts = null;

    public function mount(): void
    {
        // If user is already authenticated, redirect straight to their dashboard
        if (auth()->check()) {
            $this->redirect(app(AuthenticatedUserRedirector::class)->dashboardUrl(), navigate: true);

            return;
        }

        if (! config('app.demo_mode')) {
            return;
        }

        $this->demoAccounts = [
            'warga' => [
                'label' => 'Warga',
                'username' => DeveloperUsersSeeder::username('warga'),
            ],
            'petugas' => [
                'label' => 'Petugas',
                'username' => DeveloperUsersSeeder::username('petugas'),
            ],
            'bendahara' => [
                'label' => 'Bendahara',
                'username' => DeveloperUsersSeeder::username('bendahara'),
            ],
        ];
    }

    public function fillDemo(string $role): void
    {
        if (! config('app.demo_mode') || $this->demoAccounts === null) {
            return;
        }

        if (! array_key_exists($role, $this->demoAccounts)) {
            return;
        }

        $this->username = $this->demoAccounts[$role]['username'];
        $this->password = DeveloperUsersSeeder::password();
    }

    public function login(AuthenticateUser $authenticateUser): void
    {
        $this->username = Username::normalize($this->username);

        try {
            $validated = $this->validate();
            $user = $authenticateUser->handle($validated['username'], $validated['password'], request());
            $redirector = app(AuthenticatedUserRedirector::class);
            $rawIntended = session()->pull('url.intended') ?? app('redirect')->getIntendedUrl();
            session()->forget('url.intended');
            $intendedUrl = $redirector->authorizedIntendedUrl($rawIntended, request(), $user);

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
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/'],
            'password' => ['required', 'string'],
        ];
    }

    public function render(): View
    {
        return view('livewire.auth.login-form');
    }
}
