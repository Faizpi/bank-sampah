<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class GroceryRequestForm extends Component
{
    public string $packageId = '';

    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function updatedPackageId(): void
    {
        $this->resetValidation('packageId');
    }

    public function submit(GroceryService $service): void
    {
        $validated = $this->validate([
            'packageId' => ['required', 'integer'],
        ], [
            'packageId.required' => 'Pilih paket sembako terlebih dahulu.',
            'packageId.integer' => 'Paket sembako tidak valid.',
        ]);
        /** @var User $actor */
        $actor = auth()->user();
        $package = $service->activePackages($actor)
            ->whereKey((int) $validated['packageId'])
            ->first();

        if ($package === null) {
            $this->addError('packageId', 'Paket sembako tidak aktif atau tidak tersedia.');

            return;
        }

        if ($this->availableBalance($actor) < $package->value) {
            $this->addError('packageId', 'Saldo tersedia tidak mencukupi untuk paket ini.');

            return;
        }

        try {
            $redemption = $service->request($actor, [
                'package_id' => $this->packageId,
            ], $this->idempotencyKey);
        } catch (ValidationException $exception) {
            $this->presentRequestErrors($exception);

            return;
        }

        session()->flash('success', 'Pengajuan penukaran sembako berhasil dibuat.');
        $this->redirectRoute('citizen.grocery.show', ['redemption' => $redemption], navigate: true);
    }

    public function render(GroceryService $service): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.citizen.grocery-request-form', [
            'packages' => $service->activePackages($actor)->get(),
            'availableBalance' => $this->availableBalance($actor),
        ]);
    }

    private function availableBalance(User $actor): int
    {
        return LedgerAccount::query()
            ->where('user_id', $actor->id)
            ->first()
            ?->availableBalance() ?? 0;
    }

    private function presentRequestErrors(ValidationException $exception): void
    {
        foreach ($exception->errors() as $field => $messages) {
            $target = match ($field) {
                'package_id', 'amount', 'balance' => 'packageId',
                default => 'request',
            };

            $this->addError($target, $messages[0] ?? 'Pengajuan sembako tidak dapat diproses.');
        }
    }
}
