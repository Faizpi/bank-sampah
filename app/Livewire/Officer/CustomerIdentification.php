<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Contracts\CustomerSummary;
use App\Domain\CustomersRegions\Queries\SearchCustomers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class CustomerIdentification extends Component
{
    public string $search = '';

    public ?CustomerSummary $candidate = null;

    public bool $confirmed = false;

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'customer.view'), 403);
    }

    public function find(SearchCustomers $searchCustomers): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->reset(['candidate', 'confirmed']);
        $this->validate(['search' => ['required', 'string', 'max:120']]);
        $matches = $searchCustomers->search($actor, $this->search, 1);
        $this->candidate = $matches[0] ?? null;
    }

    public function confirm(): void
    {
        if ($this->candidate === null) {
            throw ValidationException::withMessages(['search' => 'Cari nasabah sebelum konfirmasi nama.']);
        }

        $this->confirmed = true;
    }

    public function render(): View
    {
        return view('livewire.officer.customer-identification');
    }
}
