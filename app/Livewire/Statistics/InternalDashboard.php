<?php

declare(strict_types=1);

namespace App\Livewire\Statistics;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Domain\Statistics\Services\StatisticsService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class InternalDashboard extends Component
{
    public string $start = '';

    public string $end = '';

    public string $rtId = '';

    /** @var array<string, mixed> */
    public array $statistics = [];

    public function mount(PermissionChecker $permissions, StatisticsService $service): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'statistics.internal.view'), 403);

        $this->start = today('Asia/Jakarta')->subDays(30)->toDateString();
        $this->end = today('Asia/Jakarta')->addDay()->toDateString();
        $this->refreshStatistics($service);
    }

    public function refreshStatistics(StatisticsService $service): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->validate([
            'start' => ['required', 'date_format:Y-m-d'],
            'end' => ['required', 'date_format:Y-m-d', 'after:start'],
            'rtId' => ['nullable', 'integer', 'min:1'],
        ]);

        $this->statistics = $service->internal($actor, $this->start, $this->end, $this->rtId === '' ? null : (int) $this->rtId);
    }

    public function render(VisibleUsers $visibleUsers): View
    {
        /** @var User $actor */
        $actor = auth()->user();
        $rtIds = $visibleUsers->accessibleRtIds($actor);

        return view('livewire.statistics.internal-dashboard', [
            'rts' => Rt::query()->whereIn('id', $rtIds)->orderBy('name')->get(),
        ]);
    }
}
