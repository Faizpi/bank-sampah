<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\Deposits\Data\DepositItemInput;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Services\ResolveWastePrice;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('layouts.officer')]
final class PickupTask extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    public PickupRequest $pickup;

    /** @var list<array{waste_type_id: string, condition_id: string, weight_kg: string}> */
    public array $actualItems = [];

    public string $idempotencyKey = '';

    public string $failureReason = '';

    public ?UploadedFile $evidence = null;

    public bool $failureDialogOpen = false;

    public bool $completionDialogOpen = false;

    /** @var array{number: string, value: int, occurredAt: string, status: string}|null */
    public ?array $receipt = null;

    public function mount(PickupRequest $pickup, PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        abort_unless($service->canView($actor, $pickup) && $pickup->assigned_staff_id === $actor->id, 404);
        $this->pickup = $pickup->load(['customer', 'items.wasteType', 'media', 'statusHistory.actor']);
        $this->idempotencyKey = (string) str()->uuid();
        $this->actualItems = [['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => '']];
    }

    public function begin(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->pickup = $service->begin($actor, $this->pickup);
    }

    public function markPickedUp(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->pickup = $service->markPickedUp($actor, $this->pickup);
    }

    public function addActualItem(): void
    {
        $this->actualItems[] = ['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => ''];
    }

    public function removeActualItem(int $index): void
    {
        if (count($this->actualItems) <= 1) {
            return;
        }

        unset($this->actualItems[$index]);
        $this->actualItems = array_values($this->actualItems);
    }

    public function reviewCompletion(): void
    {
        $this->validate($this->completionRules(), $this->completionMessages());
        if ($this->evidence === null && ! $this->pickup->media()->exists()) {
            $this->addError('evidence', 'Tambahkan bukti foto penjemputan melalui kamera atau galeri sebelum menyelesaikan tugas.');

            return;
        }

        $this->completionDialogOpen = true;
    }

    public function cancelCompletionReview(): void
    {
        $this->completionDialogOpen = false;
    }

    public function reportFailure(PickupService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->validate(['failureReason' => ['required', 'string', 'min:10', 'max:1000']]);
        $this->pickup = $service->cancel($actor, $this->pickup, $this->failureReason);
        $this->failureDialogOpen = false;
        session()->flash('success', 'Kegagalan tugas tercatat dan penjemputan dihentikan.');
    }

    public function openFailureReport(): void
    {
        $this->failureDialogOpen = true;
        $this->resetErrorBag('failureReason');
    }

    public function closeFailureReport(): void
    {
        $this->failureDialogOpen = false;
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
            ['required', 'file', 'max:1024', 'mimes:jpg,jpeg,png'],
            $this->completionMessages(),
        );
    }

    public function complete(PickupService $service): void
    {
        if (! $this->completionDialogOpen) {
            $this->addError('actualItems', 'Tinjau lalu konfirmasi finalisasi sebelum menyelesaikan tugas.');

            return;
        }

        /** @var User $actor */
        $actor = auth()->user();
        $this->assertAssigned($actor);
        $this->validate($this->completionRules(), $this->completionMessages());
        if ($this->evidence === null && ! $this->pickup->media()->exists()) {
            $this->addError('evidence', 'Tambahkan bukti foto penjemputan melalui kamera atau galeri sebelum menyelesaikan tugas.');

            return;
        }

        $pickupId = $this->pickup->id;
        $items = array_map(static fn (array $item): DepositItemInput => DepositItemInput::fromArray($item), $this->actualItems);
        $payloadHash = $this->completionPayloadHash($pickupId, $items);

        try {
            $this->pickup = $service->complete($actor, $this->pickup, $items, $this->idempotencyKey, $this->evidence);
        } catch (Throwable $exception) {
            if ($exception instanceof AuthorizationException) {
                throw $exception;
            }

            $durablePickup = PickupRequest::query()->with('deposit.ledgerEntries')->find($pickupId);
            $deposit = $durablePickup?->deposit;
            if (! $durablePickup instanceof PickupRequest
                || $durablePickup->status !== PickupStatus::Completed
                || ! $deposit instanceof Deposit
                || $deposit->pickup_request_id !== $durablePickup->id
                || ! $deposit->isFinal()
                || $deposit->ledgerEntries->isEmpty()
                || ! $this->hasSucceededIdempotency($actor, 'pickup.complete', $this->idempotencyKey, $payloadHash, PickupRequest::class, $pickupId)) {
                throw $exception;
            }

            $this->pickup = $durablePickup;
            $this->completeSuccessState();

            return;
        }

        $this->completeSuccessState();
    }

    /** @param list<DepositItemInput> $items */
    private function completionPayloadHash(int $pickupId, array $items): ?string
    {
        $checksum = null;
        if ($this->evidence instanceof UploadedFile) {
            $value = hash_file('sha256', $this->evidence->getRealPath());
            if (! is_string($value)) {
                return null;
            }
            $checksum = $value;
        }

        return hash('sha256', json_encode([
            'pickup' => $pickupId,
            'items' => array_map(static fn (DepositItemInput $item): array => [$item->wasteTypeId, $item->conditionId, $item->weightKg], $items),
            'evidence_checksum' => $checksum,
        ], JSON_THROW_ON_ERROR));
    }

    private function hasSucceededIdempotency(User $actor, string $scope, string $key, ?string $payloadHash, string $resultType, int $resultId): bool
    {
        return $payloadHash !== null && IdempotencyKey::query()
            ->where('actor_id', $actor->id)
            ->where('scope', $scope)
            ->where('key', $key)
            ->where('status', 'succeeded')
            ->where('payload_hash', $payloadHash)
            ->where('result_type', $resultType)
            ->where('result_id', $resultId)
            ->exists();
    }

    private function completeSuccessState(): void
    {
        $deposit = $this->pickup->deposit()->first();
        if ($deposit instanceof Deposit) {
            $this->pickup->setRelation('deposit', $deposit);
        }
        $this->receipt = $deposit instanceof Deposit && $deposit->occurred_at !== null
            ? ['number' => (string) $deposit->deposit_number, 'value' => (int) $deposit->total_value, 'occurredAt' => $deposit->occurred_at->setTimezone('Asia/Jakarta')->translatedFormat('d F Y, H:i'), 'status' => (string) $deposit->status]
            : null;
        $this->evidence = null;
        $this->completionDialogOpen = false;
        session()->flash('success', 'Penjemputan selesai dan setoran aktual telah dibuat.');
    }

    /** @return array<string, array<int, string>> */
    private function completionRules(): array
    {
        return [
            'actualItems' => ['required', 'array', 'min:1'],
            'actualItems.*.waste_type_id' => ['required', 'integer', 'min:1', 'exists:waste_types,id'],
            'actualItems.*.condition_id' => ['required', 'integer', 'min:1', 'exists:waste_conditions,id'],
            'actualItems.*.weight_kg' => ['required', 'numeric', 'gt:0', 'decimal:0,3'],
            'evidence' => ['nullable', 'file', 'max:1024', 'mimes:jpg,jpeg,png'],
        ];
    }

    /** @return array<string, string> */
    private function completionMessages(): array
    {
        return [
            'actualItems.required' => 'Minimal satu detail hasil timbang harus diisi.',
            'actualItems.*.waste_type_id.required' => 'Jenis sampah wajib dipilih.',
            'actualItems.*.waste_type_id.integer' => 'Jenis sampah yang dipilih tidak valid.',
            'actualItems.*.waste_type_id.exists' => 'Jenis sampah yang dipilih sudah tidak tersedia.',
            'actualItems.*.condition_id.required' => 'Kondisi sampah wajib dipilih.',
            'actualItems.*.condition_id.integer' => 'Kondisi sampah yang dipilih tidak valid.',
            'actualItems.*.condition_id.exists' => 'Kondisi sampah yang dipilih sudah tidak tersedia.',
            'actualItems.*.weight_kg.required' => 'Berat aktual wajib diisi.',
            'actualItems.*.weight_kg.numeric' => 'Berat aktual harus berupa angka.',
            'actualItems.*.weight_kg.gt' => 'Berat aktual harus lebih dari 0 kg.',
            'actualItems.*.weight_kg.decimal' => 'Berat aktual maksimal tiga angka di belakang koma.',
            'evidence.file' => 'Bukti foto penjemputan tidak dapat dibaca.',
            'evidence.max' => 'Ukuran bukti foto maksimal 1 MB.',
            'evidence.mimes' => 'Bukti foto harus berupa JPG, JPEG, atau PNG.',
        ];
    }

    public function render(): View
    {
        $types = WasteType::query()->where('is_active', true)->orderBy('name')->get();
        $conditions = WasteCondition::query()->where('is_active', true)->orderBy('sort_order')->get();

        return view('livewire.officer.pickup-task', [
            'types' => $types,
            'conditions' => $conditions,
            'canComplete' => $this->pickup->status === PickupStatus::PickedUp,
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
        $complete = $this->actualItems !== [];

        foreach ($this->actualItems as $item) {
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

    private function assertAssigned(User $actor): void
    {
        abort_unless($this->pickup->assigned_staff_id === $actor->id, 404);
    }
}
