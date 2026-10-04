<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Authorization\PermissionChecker;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.officer')]
final class Reports extends Component
{
    public string $start = '';

    public string $end = '';

    public string $format = 'csv';

    /** @var array{subject_count: int, deposit_count: int, total_weight_kg: string, total_value: int, plastic_weight_kg: string} */
    public array $metrics = ['subject_count' => 0, 'deposit_count' => 0, 'total_weight_kg' => '0.000', 'total_value' => 0, 'plastic_weight_kg' => '0.000'];

    public function mount(PermissionChecker $permissions): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'report.view'), 403);
        $this->start = today('Asia/Jakarta')->subDays(7)->toDateString();
        $this->end = today('Asia/Jakarta')->addDay()->toDateString();
        $this->refreshReport(app(ReportQueryService::class));
    }

    public function refreshReport(ReportQueryService $reports): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $this->metrics = $reports->aggregate($actor, ['start' => $this->start, 'end' => $this->end]);
    }

    public function export(ReportExportService $exports): void
    {
        /** @var User $actor */
        $actor = auth()->user();
        $export = $exports->export($actor, 'deposits', ['start' => $this->start, 'end' => $this->end], $this->format);
        if ($export->isAvailable()) {
            $this->redirectRoute('reports.export.download', ['export' => $export->id]);

            return;
        }
        throw ValidationException::withMessages(['export' => 'Ekspor belum tersedia. Silakan coba lagi.']);
    }

    public function render(): View
    {
        return view('livewire.treasurer.reports');
    }
}
