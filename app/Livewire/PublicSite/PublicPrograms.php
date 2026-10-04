<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Models\TargetScope;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Statistics\Services\StatisticsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class PublicPrograms extends Component
{
    public string $rtId = '';

    public function render(TargetProgressService $progress, StatisticsService $statistics): View
    {
        $targets = CollectionTarget::query()->with(['scopes.rt', 'scopes.wasteType', 'scopes.wasteCategory'])->where('is_public', true)->where('status', 'aktif')->whereDate('period_start', '<=', today())->whereDate('period_end', '>=', today())->get()->filter(function (CollectionTarget $target) use ($progress): bool {
            return $progress->aggregate($target)['subject_count'] >= $target->public_min_subjects;
        });
        $start = today()->subMonths(11)->startOfMonth()->toDateString();
        $end = today()->addDay()->toDateString();
        $publication = StatisticPublication::query()->where('publication_key', 'public-dashboard')->where('is_active', true)->first();
        $rtFilteringEnabled = $publication !== null && in_array('rt_id', $publication->dimensions, true);
        $rts = $rtFilteringEnabled ? Rt::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']) : collect();
        $selectedRtId = $rts->contains('id', (int) $this->rtId) ? (int) $this->rtId : null;
        if ($selectedRtId === null) {
            $this->rtId = '';
        }

        return view('livewire.public-site.public-programs', ['targets' => $targets->map(fn (CollectionTarget $target): array => ['name' => $target->name, 'purpose' => $target->purpose, 'scope' => $this->scopeLabel($target), 'target_weight_kg' => $target->target_weight_kg, 'progress_kg' => $progress->progress($target), 'period' => $target->period_start->format('d M Y').' – '.$target->period_end->format('d M Y')]), 'statistics' => $statistics->public($start, $end, $selectedRtId), 'rtFilteringEnabled' => $rtFilteringEnabled, 'rts' => $rts]);
    }

    private function scopeLabel(CollectionTarget $target): string
    {
        $scopes = $target->scopes->map(function (TargetScope $scope): string {
            $parts = array_filter([$scope->rt?->name, $scope->wasteType?->name, $scope->wasteCategory?->name]);

            return $parts === [] ? 'Seluruh wilayah dan jenis sampah' : implode(' · ', $parts);
        })->unique()->values();

        return $scopes->isEmpty() ? 'Seluruh wilayah dan jenis sampah' : $scopes->implode(', ');
    }
}
