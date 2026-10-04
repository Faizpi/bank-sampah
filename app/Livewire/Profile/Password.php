<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\Auth\ChangePassword;
use App\Authorization\PermissionChecker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Component;

final class Password extends Component
{
    public string $current_password = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $passwordChanged = false;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'profile.view'), 403);
    }

    public function changePassword(ChangePassword $changePassword): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $changePassword->selfService(
                $actor,
                $this->current_password,
                $this->password,
                $this->password_confirmation,
                (string) session()->getId(),
                $this->correlationId(),
            );

            $this->reset('current_password', 'password', 'password_confirmation');
            $this->passwordChanged = true;
        } catch (ValidationException $exception) {
            $this->reset('current_password', 'password', 'password_confirmation');
            $this->dispatch('password-change-invalid');

            throw $exception;
        }
    }

    public function render(): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.profile.password')
            ->layout($this->layoutFor($actor));
    }

    private function layoutFor(User $actor): string
    {
        return $actor->roles()->whereIn('name', ['petugas', 'bendahara', 'admin', 'superadmin'])->exists()
            ? 'layouts.officer'
            : 'layouts.citizen';
    }

    private function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}
