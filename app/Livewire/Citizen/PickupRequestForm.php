<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

#[Layout('layouts.citizen')]
final class PickupRequestForm extends Component
{
    use InteractsWithMediaPicker;
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

    public int $step = 1;

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
        $this->items = [['waste_type_id' => '', 'estimated_weight_kg' => '', 'estimated_quantity' => '']];
    }

    public function updatedServiceAreaId(): void
    {
        $this->resetErrorBag('selectedDate');
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

    public function removePhoto(int $index): void
    {
        if (! array_key_exists($index, $this->photos)) {
            return;
        }

        $photo = $this->photos[$index];
        if ($photo instanceof TemporaryUploadedFile) {
            $photo->delete();
        }

        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
        $this->clearPhotoErrors();
    }

    public function updatedPhotos(): void
    {
        $this->clearPhotoErrors();
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmPhotoUploads(): array
    {
        if ($this->photos === []) {
            return [];
        }

        $this->validate($this->photoRules());
        $this->clearPhotoErrors();

        return array_map(static fn (UploadedFile $photo): array => self::mediaPickerMetadata($photo), $this->photos);
    }

    public function nextStep(): void
    {
        $valid = match ($this->step) {
            1 => $this->validateStep($this->locationRules()),
            2 => $this->validateStep($this->itemRules()),
            default => true,
        };

        if ($valid) {
            $this->step = min(3, $this->step + 1);
        }
    }

    public function previousStep(): void
    {
        $this->step = max(1, $this->step - 1);
    }

    public function goToStep(int $step): void
    {
        if ($step >= 1 && $step < $this->step) {
            $this->step = $step;
        }
    }

    public function submit(PickupService $service): void
    {
        if ($this->step !== 3) {
            return;
        }

        if (! $this->validateStep([...$this->locationRules(), ...$this->itemRules()])) {
            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        try {
            $pickup = $service->submit($actor, [
                'service_area_id' => $this->serviceAreaId,
                'address' => $this->address,
                'selected_date' => $this->selectedDate,
                'notes' => $this->notes,
            ], $this->items, $this->photos, $this->idempotencyKey);
        } catch (ValidationException $exception) {
            $errors = $exception->validator->errors();
            if ($errors->has('service_area_id')) {
                $errors->add('serviceAreaId', $errors->first('service_area_id'));
                $errors->forget('service_area_id');
            }
            $this->setErrorBag($errors);
            $this->dispatch('focus-pickup-errors');

            return;
        }
        session()->flash('success', 'Pengajuan penjemputan berhasil dikirim.');
        $this->redirectRoute('citizen.pickup.show', ['pickup' => $pickup], navigate: true);
    }

    public function render(PickupService $service): View
    {
        /** @var User $actor */
        $actor = auth()->user();

        return view('livewire.citizen.pickup-request-form', [
            'areas' => $service->eligibleAreasFor($actor)->orderBy('name')->get(),
            'types' => WasteType::query()->with('category')->where('is_active', true)->whereHas('category', fn ($query) => $query->where('is_active', true))->orderBy('name')->get(),
        ]);
    }

    /** @return array<string, array<int, mixed>> */
    private function locationRules(): array
    {
        return [
            'serviceAreaId' => ['required', 'integer', 'min:1'],
            'selectedDate' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.$this->preferredDateLimit()],
            'address' => ['required', 'string', 'min:5', 'max:500'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ];
    }

    private function preferredDateLimit(): string
    {
        return today('Asia/Jakarta')->addDays((int) config('app.pickup_booking_horizon_days', 30))->toDateString();
    }

    /** @return array<string, array<int, string>> */
    private function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'integer', 'min:1'],
            'items.*.estimated_weight_kg' => ['nullable', 'numeric', 'gt:0', 'regex:/^\d+(?:\.\d{1,3})?$/'],
            'items.*.estimated_quantity' => ['nullable', 'integer', 'min:1'],
            ...$this->photoRules(),
        ];
    }

    /** @return array<string, array<int, string>> */
    private function photoRules(): array
    {
        return [
            'photos' => ['required', 'array', 'min:1', 'max:2'],
            'photos.*' => ['file', 'mimes:jpg,jpeg,png', 'max:1024'],
        ];
    }

    private function clearPhotoErrors(): void
    {
        $keys = array_values(array_filter(
            array_keys($this->getErrorBag()->getMessages()),
            static fn (string $key): bool => $key === 'photos' || str_starts_with($key, 'photos.'),
        ));

        if ($keys !== []) {
            $this->resetErrorBag($keys);
        }
    }

    /** @param array<string, array<int, string>> $rules */
    private function validateStep(array $rules): bool
    {
        try {
            $this->validate($rules);

            return true;
        } catch (ValidationException $exception) {
            $this->setErrorBag($exception->validator->errors());
            $this->dispatch('focus-pickup-errors');

            return false;
        }
    }
}
