<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Livewire\Concerns\InteractsWithMediaPicker;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.officer')]
final class GroceryTasks extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    public ?int $selectedRedemptionId = null;

    public string $recipientVerification = 'kartu_nasabah';

    public string $recipientReference = '';

    public ?UploadedFile $proof = null;

    public string $idempotencyKey = '';

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && ($permissions->allows($actor, 'grocery.prepare') || $permissions->allows($actor, 'grocery.handover')), 403);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function prepare(int $redemptionId, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->selectedRedemptionId = $redemptionId;
        $service->prepare($actor, $this->redemption($redemptionId));
        session()->flash('success', 'Paket mulai disiapkan.');
    }

    public function ready(int $redemptionId, GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->selectedRedemptionId = $redemptionId;
        $service->ready($actor, $this->redemption($redemptionId));
        session()->flash('success', 'Paket ditandai siap diambil.');
    }

    public function select(int $redemptionId): void
    {
        $this->selectedRedemptionId = $redemptionId;
        $this->recipientReference = '';
        $this->proof = null;
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function clearProof(): void
    {
        $this->clearMediaPickerUpload('proof');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmProofUpload(): array
    {
        return $this->confirmMediaPickerUpload(
            'proof',
            ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
            ['proof.required' => 'Unggah bukti serah-terima sebelum melanjutkan.'],
        );
    }

    public function handover(GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->validate([
            'recipientVerification' => ['required', 'in:kartu_nasabah,nomor_nasabah'],
            'recipientReference' => ['required', 'string', 'min:3', 'max:120'],
            'proof' => ['required', 'file', 'max:5120', 'mimes:jpg,jpeg,png,webp,pdf'],
        ]);
        if (! isset($this->proof)) {
            $this->addError('proof', 'Bukti handover wajib diunggah.');

            return;
        }
        $service->handover($actor, $this->redemption((int) $this->selectedRedemptionId), $this->recipientVerification, $this->recipientReference, $this->proof, $this->idempotencyKey);
        session()->flash('success', 'Handover tercatat dan saldo warga berhasil dikurangi.');
        $this->reset(['selectedRedemptionId', 'recipientReference', 'proof']);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function render(GroceryService $service, PermissionChecker $permissions): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $canPrepare = $permissions->allows($actor, 'grocery.prepare');
        $canHandover = $permissions->allows($actor, 'grocery.handover');
        $redemptions = $permissions->allows($actor, 'grocery.view')
            ? $service->visibleFor($actor)->whereIn('status', [GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])->latest()->get()
            : ($canHandover ? $service->readyForHandover($actor)->latest()->get() : collect());

        return view('livewire.officer.grocery-tasks', [
            'redemptions' => $redemptions,
            'canPrepare' => $canPrepare,
            'canHandover' => $canHandover,
        ]);
    }

    private function redemption(int $id): GroceryRedemption
    {
        return GroceryRedemption::query()->findOrFail($id);
    }
}
