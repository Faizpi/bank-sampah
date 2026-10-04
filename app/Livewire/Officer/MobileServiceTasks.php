<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class MobileServiceTasks extends Component
{
    public function open(int $serviceId, MobileServiceService $services): void
    {
        $actor = $this->actor();
        $service = MobileService::query()->findOrFail($serviceId);
        $services->transition($actor, $service, MobileServiceStatus::Open);
    }

    public function close(int $serviceId, MobileServiceService $services): void
    {
        $actor = $this->actor();
        $service = MobileService::query()->findOrFail($serviceId);
        $services->transition($actor, $service, MobileServiceStatus::Closed);
    }

    public function render(): View
    {
        $actor = $this->actor();

        return view('livewire.officer.mobile-service-tasks', ['services' => MobileService::query()->with(['rt', 'staff'])->whereHas('staff', fn ($query) => $query->whereKey($actor->id))->whereIn('status', [MobileServiceStatus::Published, MobileServiceStatus::Open])->orderBy('starts_at')->get()]);
    }

    private function actor(): User
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return $actor;
    }
}
