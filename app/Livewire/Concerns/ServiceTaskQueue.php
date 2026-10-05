<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Operations\Services\ClaimedServiceWorkflow;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

abstract class ServiceTaskQueue extends Component
{
    use InteractsWithMediaPicker;
    use WithFileUploads;

    #[Locked]
    public ?int $selectedId = null;

    #[Locked]
    public string $idempotencyKey = '';

    public ?UploadedFile $proof = null;

    public string $reason = '';

    public string $queueSearch = '';

    /** @var array{number: string, value: int, time: string, status: string}|null */
    #[Locked]
    public ?array $receipt = null;

    public function clearProof(): void
    {
        $this->clearMediaPickerUpload('proof');
    }

    /** @return list<array{name: string, size: int, mimeType: string, previewUrl: string}> */
    public function confirmProofUpload(): array
    {
        return $this->confirmMediaPickerUpload('proof', ['required', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp']);
    }

    abstract protected function isCash(): bool;

    public function mount(): void
    {
        abort_unless(auth()->user()?->hasRole($this->isCash() ? 'bendahara' : 'petugas'), 403);
        $this->idempotencyKey = (string) str()->uuid();
    }

    public function select(int $id): void
    {
        abort_unless($this->queue()->whereKey($id)->exists(), 404);
        $this->selectedId = $id;
        $this->proof = null;
        $this->reason = '';
        $this->receipt = null;
        $this->idempotencyKey = (string) str()->uuid();
        $this->resetValidation();
    }

    public function claim(): void
    {
        app(ClaimedServiceWorkflow::class)->claim($this->actor(), $this->selected());
        session()->flash('success', 'Tugas berhasil diambil. Pastikan penyerahan fisik hanya dilakukan satu kali.');
    }

    public function release(): void
    {
        app(ClaimedServiceWorkflow::class)->release($this->actor(), $this->selected(), $this->reason);
        $this->selectedId = null;
        session()->flash('success', 'Tugas kembali ke antrean; saldo tetap ditahan.');
    }

    public function ready(): void
    {
        $request = $this->selected();
        abort_unless($request instanceof GroceryRedemption, 403);
        app(ClaimedServiceWorkflow::class)->ready($this->actor(), $request);
        session()->flash('success', 'Paket siap diambil.');
    }

    public function unavailable(): void
    {
        $request = $this->selected();
        abort_unless($request instanceof GroceryRedemption, 403);
        app(ClaimedServiceWorkflow::class)->cancel($this->actor(), $request, $this->reason, true);
        $this->selectedId = null;
        session()->flash('success', 'Paket tidak tersedia; saldo yang ditahan sudah dilepas.');
    }

    public function reportException(): void
    {
        app(ClaimedServiceWorkflow::class)->reportException($this->actor(), $this->selected(), $this->reason);
        session()->flash('success', 'Transaksi dikunci untuk pemeriksaan Admin. Jangan serahkan ulang.');
    }

    public function confirmCancellation(): void
    {
        app(ClaimedServiceWorkflow::class)->cancel($this->actor(), $this->selected(), 'Warga meminta batal dan pelaksana memastikan manfaat belum diserahkan.');
        $this->selectedId = null;
        session()->flash('success', 'Pembatalan dikonfirmasi; saldo tertahan sudah dilepas.');
    }

    public function complete(): void
    {
        $this->validate(['proof' => ['nullable', 'image', 'max:5120', 'mimes:jpg,jpeg,png,webp']]);
        $request = app(ClaimedServiceWorkflow::class)->complete($this->actor(), $this->selectedForCompletion(), $this->proof, $this->idempotencyKey);
        $recordedAt = $request instanceof WithdrawalRequest ? $request->paid_at : $request->handed_over_at;
        if (! $recordedAt instanceof CarbonInterface) {
            throw ValidationException::withMessages(['workflow' => 'Waktu pencatatan tidak lengkap; minta pemeriksaan Admin.']);
        }
        $this->receipt = ['number' => $request->request_number, 'value' => $this->isCash() ? $request->amount : $request->value_snapshot, 'time' => $recordedAt->translatedFormat('d F Y H:i'), 'status' => $request->status->value];
        $this->selectedId = null;
        $this->proof = null;
        session()->flash('success', 'Penyerahan sudah tercatat. Jangan lakukan penyerahan kedua.');
    }

    /** @return Builder<WithdrawalRequest>|Builder<GroceryRedemption> */
    private function queue(): Builder
    {
        return $this->isCash()
            ? app(WithdrawalService::class)->payableFor($this->actor())
            : app(GroceryService::class)->readyForHandover($this->actor());
    }

    private function selected(): WithdrawalRequest|GroceryRedemption
    {
        abort_unless($this->selectedId !== null, 404);

        return $this->queue()->whereKey($this->selectedId)->firstOrFail();
    }

    private function selectedForCompletion(): WithdrawalRequest|GroceryRedemption
    {
        abort_unless($this->selectedId !== null, 404);
        $actor = $this->actor();

        return $this->isCash()
            ? app(WithdrawalService::class)->visibleFor($actor)->whereKey($this->selectedId)->where('payer_id', $actor->id)->firstOrFail()
            : app(GroceryService::class)->visibleFor($actor)->whereKey($this->selectedId)->where('claimed_by_id', $actor->id)->firstOrFail();
    }

    private function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    public function render(): View
    {
        $requests = $this->queue()->with(['customer.customerProfile', 'serviceArea', 'balanceHold'])
            ->when(trim($this->queueSearch) !== '', fn (Builder $q): Builder => $q->where(fn (Builder $search): Builder => $search->where('request_number', 'like', '%'.trim($this->queueSearch).'%')->orWhereHas('customer', fn (Builder $customer): Builder => $customer->where('name', 'like', '%'.trim($this->queueSearch).'%'))))
            ->orderBy('created_at')->get();

        return view('livewire.service-task-queue', ['requests' => $requests, 'selected' => $requests->firstWhere('id', $this->selectedId), 'cash' => $this->isCash()]);
    }
}
