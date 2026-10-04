<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.officer')]
final class DepositForm extends Component
{
    use WithFileUploads;

    public int $customerId;

    public ?int $mobileServiceId = null;

    public ?int $assistedServiceId = null;

    public ?Deposit $draft = null;

    /** @var list<array{waste_type_id: int|string, condition_id: int|string, weight_kg: string}> */
    public array $items = [];

    public string $idempotencyKey = '';

    public ?UploadedFile $evidence = null;

    public function mount(int $customerId, PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.create'), 403);
        $this->customerId = $customerId;
        $this->mobileServiceId = request()->integer('mobileServiceId') ?: null;
        $this->assistedServiceId = request()->integer('assistedServiceId') ?: null;
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
        $this->validateItems();
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $customer */
        $customer = User::query()->findOrFail($this->customerId);
        $this->draft ??= $service->createDraft($actor, $customer, $this->mobileServiceId === null ? 'langsung' : 'keliling', null, $this->mobileServiceId === null ? null : MobileService::query()->findOrFail($this->mobileServiceId));
        $service->replaceDraftItems($actor, $this->draft, array_map(static fn (array $item): DepositItemInput => DepositItemInput::fromArray($item), $this->items));
        $this->draft = $this->draft->fresh('items');
        session()->flash('success', 'Draf setoran tersimpan.');
    }

    public function finalize(DepositService $service): void
    {
        $this->validateItems();
        $this->validate([
            'evidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        /** @var User $actor */
        $actor = auth()->user();
        /** @var User $customer */
        $customer = User::query()->findOrFail($this->customerId);
        $this->draft ??= $service->createDraft($actor, $customer, $this->mobileServiceId === null ? 'langsung' : 'keliling', null, $this->mobileServiceId === null ? null : MobileService::query()->findOrFail($this->mobileServiceId));
        $mobileService = $this->mobileServiceId === null ? null : MobileService::query()->findOrFail($this->mobileServiceId);
        $this->draft = $this->assistedServiceId === null
            ? $service->finalize($actor, $this->draft, $this->idempotencyKey, $this->items, $this->evidence, $mobileService)
            : $service->finalizeAndLinkAssisted($actor, $this->draft, $this->idempotencyKey, $this->assistedServiceId, $this->items, $this->evidence, $mobileService);
        $this->evidence = null;
        session()->flash('success', 'Setoran berhasil difinalisasi.');
    }

    /** @return array<string, array<int, string>> */
    private function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'integer', 'min:1'],
            'items.*.condition_id' => ['required', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required', 'numeric', 'gt:0', 'regex:/^\\d+(?:\\.\\d{1,3})?$/'],
        ];
    }

    private function validateItems(): void
    {
        $this->validate($this->itemRules());
    }

    public function render(): View
    {
        return view('livewire.officer.deposit-form', [
            'types' => WasteType::query()->with('conditions')->where('is_active', true)->whereHas('category', static fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'conditions' => WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get(),
        ]);
    }
}
