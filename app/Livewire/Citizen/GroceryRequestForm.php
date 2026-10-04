<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Groceries\Services\GroceryService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class GroceryRequestForm extends Component
{
    public string $packageId = '';

    public string $sourceType = 'saldo';

    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function submit(GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $redemption = $service->request($actor, [
            'package_id' => $this->packageId,
            'source_type' => $this->sourceType,
        ], $this->idempotencyKey);
        session()->flash('success', 'Pengajuan penukaran sembako berhasil dibuat.');
        $this->redirectRoute('citizen.grocery.show', ['redemption' => $redemption], navigate: true);
    }

    public function render(GroceryService $service): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.citizen.grocery-request-form', [
            'packages' => $service->activePackages($actor)->get(),
        ]);
    }
}
