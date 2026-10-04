<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Statistics\Services\StatisticsService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class PublicPrograms extends Component
{
    public function render(TargetProgressService $progress, StatisticsService $statistics): View
    {
        $targets = CollectionTarget::query()->with('scopes')->where('is_public', true)->where('status', 'aktif')->whereDate('period_start', '<=', today())->whereDate('period_end', '>=', today())->get()->filter(function (CollectionTarget $target) use ($progress): bool {
            return $progress->aggregate($target)['subject_count'] >= $target->public_min_subjects;
        });

        return view('livewire.public-site.public-programs', ['targets' => $targets->map(fn (CollectionTarget $target): array => ['name' => $target->name, 'purpose' => $target->purpose, 'target_weight_kg' => $target->target_weight_kg, 'progress_kg' => $progress->progress($target), 'period' => $target->period_start->format('d M Y').' – '.$target->period_end->format('d M Y')]), 'statistics' => $statistics->public(today()->subYear()->toDateString(), today()->addDay()->toDateString())]);
    }
}
