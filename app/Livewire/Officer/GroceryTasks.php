<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Groceries\Enums\GrocerySource;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('layouts.officer')]
final class GroceryTasks extends Component
{
    use WithFileUploads;

    public ?int $selectedRedemptionId = null;

    public string $recipientVerification = 'kartu_nasabah';

    public string $recipientReference = '';

    public ?UploadedFile $proof = null;

    public string $idempotencyKey = '';

    public string $freeAidCustomerId = '';

    public string $freeAidPackageId = '';

    public string $freeAidIdempotencyKey = '';

    public function mount(): void
    {
        $this->idempotencyKey = (string) str()->uuid();
        $this->freeAidIdempotencyKey = (string) str()->uuid();
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

    public function createFreeAid(GroceryService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->validate([
            'freeAidCustomerId' => ['required', 'integer', 'min:1'],
            'freeAidPackageId' => ['required', 'integer', 'min:1'],
        ]);
        $service->request($actor, [
            'customer_id' => (int) $this->freeAidCustomerId,
            'package_id' => (int) $this->freeAidPackageId,
            'source_type' => GrocerySource::FreeAid->value,
        ], $this->freeAidIdempotencyKey);
        session()->flash('success', 'Bantuan gratis berhasil dicatat tanpa hold dan tanpa saldo keluar.');
        $this->reset(['freeAidCustomerId', 'freeAidPackageId']);
        $this->freeAidIdempotencyKey = (string) str()->uuid();
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
        session()->flash('success', 'Handover tercatat dan saldo keluar dibuat bila sumbernya saldo.');
        $this->reset(['selectedRedemptionId', 'recipientReference', 'proof']);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function render(GroceryService $service, PermissionChecker $permissions, VisibleUsers $visibleUsers): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $customers = $visibleUsers->queryFor($actor)
            ->where('users.id', '<>', $actor->id)
            ->whereHas('customerProfile')
            ->orderBy('name')
            ->get(['users.id', 'users.name']);

        return view('livewire.officer.grocery-tasks', [
            'redemptions' => $service->visibleFor($actor)->whereIn('status', [GroceryStatus::Approved, GroceryStatus::Preparing, GroceryStatus::ReadyForPickup])->latest()->get(),
            'customerOptions' => $customers->pluck('name', 'id')->all(),
            'packageOptions' => $service->activePackages($actor)->get()->pluck('name', 'id')->all(),
            'canCreateFreeAid' => $permissions->allows($actor, 'grocery.request'),
        ]);
    }

    private function redemption(int $id): GroceryRedemption
    {
        return GroceryRedemption::query()->findOrFail($id);
    }
}
