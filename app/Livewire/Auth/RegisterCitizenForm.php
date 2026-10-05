<?php

declare(strict_types=1);

namespace App\Livewire\Auth;

use App\Actions\Auth\RegisterCitizen;
use App\Domain\CustomersRegions\Models\Rt;
use App\Support\Auth\Username;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class RegisterCitizenForm extends Component
{
    public string $name = '';

    public string $username = '';

    public string $phone = '';

    public string $password = '';

    public string $password_confirmation = '';

    public string $rt_id = '';

    public string $address = '';

    public bool $terms_accepted = false;

    public bool $registered = false;

    public function register(RegisterCitizen $registerCitizen): void
    {
        $this->username = Username::normalize($this->username);
        $this->phone = (trim($this->phone) === '') ? '' : $this->normalizeOptionalPhone($this->phone);
        $this->name = preg_replace('/\s+/u', ' ', trim($this->name)) ?? trim($this->name);
        $this->address = trim($this->address);

        try {
            $validated = $this->validate();
        } catch (ValidationException $exception) {
            $this->dispatch('registration-invalid');

            throw $exception;
        }

        $validated['rt_id'] = (int) $validated['rt_id'];

        $registerCitizen->handle($validated);

        $this->registered = true;
        $this->reset(['password', 'password_confirmation', 'terms_accepted']);
    }

    /** @return array<string, list<string|object>> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'min:2', 'max:120', 'regex:/\S/u', 'not_regex:/[\p{Cc}]/u'],
            'username' => ['required', 'string', 'min:3', 'max:60', 'regex:/^[a-z0-9._-]+$/', Rule::unique('users', 'username')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'phone' => ['nullable', 'string', 'regex:/^62[0-9]{8,16}$/', Rule::unique('users', 'phone')],
            'rt_id' => ['required', 'integer', Rule::exists('rt', 'id')],
            'address' => ['required', 'string', 'min:5', 'max:500', 'regex:/\S/u', 'not_regex:/[\p{Cc}]/u'],
            'terms_accepted' => ['accepted'],
        ];
    }

    private function normalizeOptionalPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone) ?? '';

        if ($digits === '') {
            return '';
        }

        return str_starts_with($digits, '0') ? '62'.substr($digits, 1) : $digits;
    }

    public function render(): View
    {
        return view('livewire.auth.register-citizen-form', [
            'rts' => Rt::query()
                ->with(['rw.dusun'])
                ->where('is_active', true)
                ->whereHas('rw', fn ($query) => $query->where('is_active', true)->whereHas('dusun', fn ($query) => $query->where('is_active', true)))
                ->orderBy('code')
                ->get(),
        ]);
    }
}
