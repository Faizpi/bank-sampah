<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.citizen')]
final class PickupRequestForm extends Component
{
    use WithFileUploads;

    public string $address = '';

    public string $selectedDate = '';

    public string $notes = '';

    public string $serviceAreaId = '';

    /** @var list<array{waste_type_id: string, estimated_weight_kg: string, estimated_quantity: string}> */
    public array $items = [];

    /** @var list<UploadedFile> */
    public array $photos = [];

    public string $idempotencyKey = '';

    public function mount(): void
    {
        $this->selectedDate = today()->addDay()->toDateString();
        $this->idempotencyKey = (string) str()->uuid();
        $this->items = [['waste_type_id' => '', 'estimated_weight_kg' => '', 'estimated_quantity' => '']];
    }

    public function addItem(): void
    {
        $this->items[] = ['waste_type_id' => '', 'estimated_weight_kg' => '', 'estimated_quantity' => ''];
    }

    public function removeItem(int $index): void
    {
        unset($this->items[$index]);
        $this->items = array_values($this->items);
    }

    public function submit(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $pickup = $service->submit($actor, [
            'service_area_id' => $this->serviceAreaId,
            'address' => $this->address,
            'selected_date' => $this->selectedDate,
            'notes' => $this->notes,
        ], $this->items, $this->photos, $this->idempotencyKey);
        session()->flash('success', 'Pengajuan penjemputan berhasil dikirim.');
        $this->redirectRoute('citizen.pickup.show', ['pickup' => $pickup], navigate: true);
    }

    public function render(): View
    {
        return view('livewire.citizen.pickup-request-form', [
            'areas' => ServiceArea::query()->where('is_active', true)->whereHas('rts', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
            'types' => WasteType::query()->with('category')->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
        ]);
    }
}
