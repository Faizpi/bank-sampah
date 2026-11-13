<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Models\User;
use App\Support\Auth\AuthenticatedUserRedirector;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.officer')]
final class Dashboard extends Component
{
    use WithPagination;

    public string $taskTab = 'waiting';

    public string $taskType = 'pickup';

    public function updatedTaskType(): void
    {
        $this->validateOnly('taskType', ['taskType' => ['required', 'in:pickup,grocery,deposit,mobile']]);
        if (in_array($this->taskType, ['deposit', 'mobile'], true)) {
            $this->taskTab = 'mine';
        }
        $this->resetPage('officerTasks');
    }

    public function updatedTaskTab(): void
    {
        $this->validateOnly('taskTab', ['taskTab' => ['required', 'in:waiting,mine']]);
        $this->resetPage('officerTasks');
    }

    public function mount(PermissionChecker $permissions, AuthenticatedUserRedirector $redirector): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();

        abort_unless($actor instanceof User && $permissions->allows($actor, 'user.view'), 403);

        if ($actor->hasRole('admin', 'bendahara', 'warga') && ! $actor->hasRole('petugas')) {
            $this->redirect($redirector->dashboardUrl($actor), navigate: true);

            return;
        }
    }

    public function claimPickup(int $id): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $pickup = app(PickupService::class)->claim($actor, PickupRequest::findOrFail($id));
        $this->redirectRoute('officer.pickup.task', ['pickup' => $pickup]);
    }

    public function render(PermissionChecker $permissions, GroceryService $groceries): View
    {
        if (! in_array($this->taskType, ['pickup', 'grocery', 'deposit', 'mobile'], true)) {
            $this->taskType = 'pickup';
            $this->addError('taskType', 'Pilih jenis tugas yang tersedia.');
        }
        if (! in_array($this->taskTab, ['waiting', 'mine'], true)) {
            $this->taskTab = 'waiting';
            $this->addError('taskTab', 'Pilih antrean Menunggu atau Tugas saya.');
        }
        /** @var User $actor */
        $actor = auth()->user();
        if (in_array($this->taskType, ['deposit', 'mobile'], true)) {
            $this->taskTab = 'mine';
        }
        $pickupStatuses = [PickupStatus::Scheduled, PickupStatus::EnRoute, PickupStatus::PickedUp];
        $pickups = app(PickupService::class);
        $query = match ($this->taskType) {
            'pickup' => $pickups->visibleFor($actor)->with('customer')
                ->when($this->taskTab === 'waiting', fn (Builder $query): Builder => $query->where('status', PickupStatus::Waiting))
                ->when($this->taskTab === 'mine', fn (Builder $query): Builder => $query->where('assigned_staff_id', $actor->id)->whereIn('status', $pickupStatuses))->orderBy('selected_date'),
            'grocery' => $groceries->readyForHandover($actor)->with('customer')
                ->when($this->taskTab === 'waiting', fn (Builder $query): Builder => $query->whereNull('claimed_by_id'))
                ->when($this->taskTab === 'mine', fn (Builder $query): Builder => $query->where('claimed_by_id', $actor->id))->orderBy('created_at'),
            'deposit' => Deposit::query()->with('customer.customerProfile')->where('staff_id', $actor->id)->where('status', Deposit::STATUS_DRAFT)->latest('occurred_at'),
            default => MobileService::query()->whereHas('staff', fn (Builder $staff): Builder => $staff->whereKey($actor->id))
                ->where(fn (Builder $query): Builder => $query->where('status', MobileServiceStatus::Open)->orWhere(fn (Builder $scheduled): Builder => $scheduled->where('status', MobileServiceStatus::Published)->where('ends_at', '>=', now())))
                ->orderBy('starts_at'),
        };
        $urgentPickup = $pickups->visibleFor($actor)->with('customer')->where('assigned_staff_id', $actor->id)->whereIn('status', $pickupStatuses)->whereDate('scheduled_date', '<=', today())->orderBy('scheduled_date')->first();
        $openMobile = MobileService::query()->whereHas('staff', fn (Builder $staff): Builder => $staff->whereKey($actor->id))->where('status', MobileServiceStatus::Open)->orderBy('ends_at')->first();
        $priorityTask = $urgentPickup !== null
            ? ['label' => $urgentPickup->scheduled_date->isBefore(today()) ? 'Penjemputan terlambat' : 'Penjemputan hari ini', 'description' => $urgentPickup->customer->name.' · '.$urgentPickup->address, 'href' => route('officer.pickup.task', $urgentPickup)]
            : ($openMobile !== null ? ['label' => $openMobile->ends_at->isPast() ? 'Tutup layanan yang sudah berakhir' : 'Layanan keliling sedang dibuka', 'description' => $openMobile->point, 'href' => route('officer.mobile-services')] : null);

        $tasks = $query->paginate(10, ['*'], 'officerTasks');
        if ($tasks->currentPage() > $tasks->lastPage()) {
            $this->setPage($tasks->lastPage(), 'officerTasks');
            $tasks = $query->paginate(10, ['*'], 'officerTasks');
        }

        return view('livewire.officer.dashboard', [
            'tasks' => $tasks,
            'priorityTask' => $priorityTask,
            'identificationHref' => route('officer.customer-identification'),
        ]);
    }
}
