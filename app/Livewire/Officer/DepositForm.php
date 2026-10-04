<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Models\DepositItem;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.officer')]
final class DepositForm extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    #[Locked]
    public int $customerId;

    #[Locked]
    public ?int $mobileServiceId = null;

    #[Locked]
    public ?int $assistedServiceId = null;

    #[Locked]
    public ?Deposit $draft = null;

    /** @var list<array{waste_type_id: int|string, condition_id: int|string, weight_kg: string}> */
    public array $items = [];

    #[Locked]
    public string $idempotencyKey = '';

    public ?UploadedFile $evidence = null;

    public bool $finalizationReviewOpen = false;

    public function mount(int $customerId, PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'deposit.create'), 403);

        $draftId = request()->query('draftId');
        if ($draftId !== null) {
            $draftIdValue = filter_var($draftId, FILTER_VALIDATE_INT);
            abort_unless(is_int($draftIdValue) && $draftIdValue > 0, 404);
            $draft = Deposit::query()
                ->with('items')
                ->whereKey($draftIdValue)
                ->where('staff_id', $actor->id)
                ->where('customer_id', $customerId)
                ->where('status', Deposit::STATUS_DRAFT)
                ->first();
            abort_unless($draft instanceof Deposit, 404);

            $this->draft = $draft;
            $this->customerId = $draft->customer_id;
            $this->mobileServiceId = $draft->mobile_service_id;
            $this->assistedServiceId = null;
            $this->items = $draft->items->map(static fn (DepositItem $item): array => [
                'waste_type_id' => $item->waste_type_id,
                'condition_id' => $item->waste_condition_id,
                'weight_kg' => (string) $item->weight_kg,
            ])->values()->all();
            $this->idempotencyKey = (string) str()->uuid();

            return;
        }

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

    public function clearEvidence(): void
    {
        $this->clearMediaPickerUpload('evidence');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmEvidenceUpload(): array
    {
        return $this->confirmMediaPickerUpload(
            'evidence',
            ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            ['evidence.required' => 'Unggah bukti transaksi sebelum melanjutkan.'],
        );
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

    public function reviewFinalization(): void
    {
        $this->validateItems();
        $this->validate([
            'evidence' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        $this->finalizationReviewOpen = true;
    }

    public function cancelFinalizationReview(): void
    {
        $this->finalizationReviewOpen = false;
    }

    public function finalize(DepositService $service): void
    {
        if (! $this->finalizationReviewOpen) {
            $this->addError('items', 'Tinjau lalu konfirmasi finalisasi sebelum mencatat setoran.');

            return;
        }

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
        $draftId = $this->draft->id;

        try {
            $this->draft = $this->assistedServiceId === null
                ? $service->finalize($actor, $this->draft, $this->idempotencyKey, $this->items, $this->evidence, $mobileService)
                : $service->finalizeAndLinkAssisted($actor, $this->draft, $this->idempotencyKey, $this->assistedServiceId, $this->items, $this->evidence, $mobileService);
        } catch (Throwable $exception) {
            $durableDraft = Deposit::query()->find($draftId);
            if (! $durableDraft instanceof Deposit || $durableDraft->isDraft()) {
                throw $exception;
            }

            $this->draft = $durableDraft->fresh('items');
            $this->evidence = null;
            $this->finalizationReviewOpen = false;
            session()->flash('success', $this->draft->isPendingReview()
                ? 'Setoran berhasil dicatat dan menunggu persetujuan pemeriksa.'
                : 'Setoran berhasil difinalisasi.');

            return;
        }

        $this->evidence = null;
        $this->finalizationReviewOpen = false;
        session()->flash('success', $this->draft->isPendingReview()
            ? 'Setoran bernilai tinggi menunggu persetujuan pemeriksa. Saldo belum ditambahkan.'
            : 'Setoran berhasil difinalisasi.');
    }

    /** @return array<string, array<int, string>> */
    private function itemRules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.waste_type_id' => ['required', 'integer', 'min:1'],
            'items.*.condition_id' => ['required', 'integer', 'min:1'],
            'items.*.weight_kg' => ['required', 'numeric', 'gt:0', 'max:'.(string) config('app.deposit_max_item_weight_kg'), 'regex:/^\\d+(?:\\.\\d{1,3})?$/'],
        ];
    }

    private function validateItems(): void
    {
        $this->validate($this->itemRules());
    }

    public function render(): View
    {
        $types = WasteType::query()->with('conditions')->where('is_active', true)->whereHas('category', static fn ($query) => $query->where('is_active', true))->orderBy('name')->get();
        $conditions = WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('livewire.officer.deposit-form', [
            'types' => $types,
            'conditions' => $conditions,
            'pricePreview' => $this->pricePreview($types, $conditions),
        ]);
    }

    /**
     * @param  Collection<int, WasteType>  $types
     * @param  Collection<int, WasteCondition>  $conditions
     * @return array{lines: list<array{name: string, condition: string, weight: string, subtotal: int}>, total: int, complete: bool}
     */
    private function pricePreview(Collection $types, Collection $conditions): array
    {
        $lines = [];
        $total = 0;
        $complete = $this->items !== [];

        foreach ($this->items as $item) {
            $type = $types->firstWhere('id', (int) $item['waste_type_id']);
            $condition = $conditions->firstWhere('id', (int) $item['condition_id']);
            $weight = $item['weight_kg'];
            if ($type === null || $condition === null || $weight === '') {
                $complete = false;

                continue;
            }

            try {
                $price = app(ResolveWastePrice::class)->resolve($type, (int) $condition->id, now('Asia/Jakarta'));
                $snapshot = $price->snapshot()->withWeight($weight);
            } catch (Throwable) {
                $complete = false;

                continue;
            }

            $subtotal = (int) $snapshot->subtotal;
            $total += $subtotal;
            $lines[] = [
                'name' => $type->name,
                'condition' => $condition->name,
                'weight' => (string) $snapshot->weightKg,
                'subtotal' => $subtotal,
            ];
        }

        return ['lines' => $lines, 'total' => $total, 'complete' => $complete && $lines !== []];
    }
}
