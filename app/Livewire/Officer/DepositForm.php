<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class DepositForm extends Component
{
    public int $customerId;

    public ?Deposit $draft = null;

    /** @var list<array{waste_type_id: int|string, condition_id: int|string, weight_kg: string}> */
    public array $items = [];

    public string $idempotencyKey = '';

    public function mount(int $customerId, PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.create'), 403);
        $this->customerId = $customerId;
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function addItem(): void
    {
        $this->items[] = ['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function saveDraft(DepositService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $customer */
        $customer = User::query()->findOrFail($this->customerId);
        $this->draft ??= $service->createDraft($actor, $customer);
        $service->replaceDraftItems($actor, $this->draft, array_map(static fn (array $item): DepositItemInput => DepositItemInput::fromArray($item), $this->items));
        $this->draft = $this->draft->fresh('items');
        session()->flash('success', 'Draf setoran tersimpan.');
    }

    public function finalize(DepositService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $customer */
        $customer = User::query()->findOrFail($this->customerId);
        $this->draft ??= $service->createDraft($actor, $customer);
        $this->draft = $service->finalize($actor, $this->draft, $this->idempotencyKey, $this->items);
        session()->flash('success', 'Setoran berhasil difinalisasi.');
    }

    public function render(): View
    {
        return view('livewire.officer.deposit-form', [
            'types' => WasteType::query()->with('conditions')->where('is_active', true)->whereHas('category', static fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'conditions' => WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
