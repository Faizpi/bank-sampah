<?php

declare(strict_types=1);

namespace App\Livewire\Profile;

use App\Actions\Auth\ChangePassword;
use App\Authorization\PermissionChecker;
use App\Domain\Identity\Actions\UpdateSelfServiceProfile;
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

    public string $name = '';

    public string $phone = '';

    public string $address = '';

    public string $region = '';

    public string $rt = '';

    public bool $profileChanged = false;

    public bool $canUpdateAddress = false;

    public bool $passwordChanged = false;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'profile.view'), 403);

        $actor->loadMissing('customerProfile.rt.rw.dusun');
        $this->name = $actor->name;
        $this->phone = (string) $actor->phone;
        $this->address = $actor->customerProfile === null ? '' : (string) $actor->customerProfile->address;
        $this->region = $this->regionLabel($actor);
        $this->rt = $actor->customerProfile === null || $actor->customerProfile->rt === null
            ? 'Belum ditetapkan'
            : $actor->customerProfile->rt->name;
        $this->canUpdateAddress = $permissions->allows($actor, 'customer.update') && $actor->customerProfile !== null;
    }

    public function updateProfile(UpdateSelfServiceProfile $updateSelfServiceProfile): void
    {
        /** @var User $actor */
        $actor = auth()->user();

        try {
            $data = [
                'name' => $this->name,
                'phone' => $this->phone,
            ];
            if ($this->canUpdateAddress) {
                $data['address'] = $this->address;
            }

            $updated = $updateSelfServiceProfile->handle($actor, $data);

            $this->name = $updated->name;
            $this->phone = (string) $updated->phone;
            $this->address = (string) $updated->customerProfile?->address;
            $this->profileChanged = true;
        } catch (ValidationException $exception) {
            $this->dispatch('profile-update-invalid');

            throw $exception;
        }
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

    private function regionLabel(User $actor): string
    {
        $rt = $actor->customerProfile?->rt;
        $rw = $rt?->rw;
        $dusun = $rw?->dusun;

        return collect([$dusun?->name, $rw?->name, $rt?->name])
            ->filter(static fn (mixed $value): bool => is_string($value) && $value !== '')
            ->implode(' · ');
    }

    private function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}
