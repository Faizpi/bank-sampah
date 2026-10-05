<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Reports\Policies\ReportTypeAccess;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Reports\Services\ReportQueryService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Locked;
use Livewire\Component;

#[Layout('layouts.officer')]
final class Reports extends Component
{
    public string $start = '';

    public string $end = '';

    public string $reportType = 'withdrawals';

    #[Locked]
    public string $surface = ReportTypeAccess::TREASURER;

    public string $period = 'today';

    public string $month = '';

    public string $year = '';

    public string $status = '';

    public string $search = '';

    public string $serviceAreaId = '';

    /** @var array<string, string> */
    public array $reportTypes = [];

    /** @var list<array<string, string>> */
    public array $rows = [];

    /** @var list<array{key: string, label: string, format: string}> */
    public array $metricDefinitions = [];

    /** @var array<string, int|string> */
    public array $metrics = [];

    /** @var array<string, string> */
    public array $statusOptions = [
        'menunggu_verifikasi' => 'Menunggu verifikasi',
        'menunggu_pemeriksaan' => 'Menunggu pemeriksaan',
        'diterima' => 'Diterima',
        'dijadwalkan' => 'Dijadwalkan',
        'menuju_lokasi' => 'Menuju lokasi',
        'dijemput' => 'Sudah dijemput',
        'selesai' => 'Selesai',
        'siap_diambil' => 'Siap diambil',
        'sudah_dibayar' => 'Sudah dibayar',
        'ditolak' => 'Ditolak',
        'dibatalkan' => 'Dibatalkan',
        'kedaluwarsa' => 'Kedaluwarsa',
    ];

    public function mount(PermissionChecker $permissions, ReportTypeAccess $reportTypes): void
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $permissions->allows($actor, 'report.view'), 403);
        $this->reportTypes = $reportTypes->optionsFor($this->surface);
        $this->reportType = $reportTypes->defaultFor($this->surface)->value;
        $today = today('Asia/Jakarta');
        $this->month = $today->format('m');
        $this->year = $today->format('Y');
        $this->start = $today->toDateString();
        $this->end = $today->toDateString();
        $this->refreshReport(app(ReportQueryService::class), $reportTypes);
    }

    public function refreshReport(ReportQueryService $reports, ReportTypeAccess $reportTypes): void
    {
        $reportTypes->assertAllowed($this->surface, $this->reportType);
        /** @var User $actor */
        $actor = auth()->user();
        [$start, $end] = $this->periodRange();
        $filters = ['start' => $start, 'end' => $end];
        if ($this->status !== '') {
            $filters['status'] = $this->status;
        }
        if ($this->search !== '') {
            $filters['search'] = $this->search;
        }
        if ($this->serviceAreaId !== '') {
            $filters['service_area_id'] = (int) $this->serviceAreaId;
        }
        $this->metricDefinitions = $reports->summaryContract($this->reportType);
        $this->metrics = $reports->aggregate($actor, $filters, $this->reportType);
        $this->rows = $reports->displayRows($actor, $this->reportType, $filters);
    }

    public function setPeriod(string $preset, ReportQueryService $reports, ReportTypeAccess $reportTypes): void
    {
        if (in_array($preset, ['today', 'week', 'month', 'custom'], true)) {
            $this->period = $preset;
        }
        [$start, $end] = $this->periodRange();
        $this->start = $start;
        $this->end = CarbonImmutable::parse($end, 'Asia/Jakarta')->subDay()->toDateString();
        $this->refreshReport($reports, $reportTypes);
    }

    public function export(ReportExportService $exports, ReportTypeAccess $reportTypes): void
    {
        $reportTypes->assertAllowed($this->surface, $this->reportType);
        /** @var User $actor */
        $actor = auth()->user();
        [$start, $end] = $this->periodRange();
        $filters = ['start' => $start, 'end' => $end];
        if ($this->status !== '') {
            $filters['status'] = $this->status;
        }
        if ($this->search !== '') {
            $filters['search'] = $this->search;
        }
        if ($this->serviceAreaId !== '') {
            $filters['service_area_id'] = (int) $this->serviceAreaId;
        }
        $export = $exports->export($actor, $this->reportType, $filters, 'xlsx');
        if ($export->isAvailable()) {
            $this->redirectRoute('reports.export.download', ['export' => $export->id]);

            return;
        }
        throw ValidationException::withMessages(['export' => 'Ekspor belum tersedia. Silakan coba lagi.']);
    }

    public function render(): View
    {
        return view('livewire.treasurer.reports', [
            'serviceAreas' => ServiceArea::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id'),
            'years' => array_combine(
                range((int) today('Asia/Jakarta')->year - 2, (int) today('Asia/Jakarta')->year + 1),
                range((int) today('Asia/Jakarta')->year - 2, (int) today('Asia/Jakarta')->year + 1),
            ),
            'months' => [
                '01' => 'Januari', '02' => 'Februari', '03' => 'Maret', '04' => 'April',
                '05' => 'Mei', '06' => 'Juni', '07' => 'Juli', '08' => 'Agustus',
                '09' => 'September', '10' => 'Oktober', '11' => 'November', '12' => 'Desember',
            ],
        ]);
    }

    /** @return array{0: string, 1: string} */
    private function periodRange(?CarbonInterface $today = null): array
    {
        $today = CarbonImmutable::instance($today ?? today('Asia/Jakarta'))->setTimezone('Asia/Jakarta');

        return match ($this->period) {
            'today' => [$today->toDateString(), $today->addDay()->toDateString()],
            'week' => [$today->startOfWeek()->toDateString(), $today->addDay()->toDateString()],
            'month' => $this->monthRange(),
            default => [$this->start, CarbonImmutable::parse($this->end, 'Asia/Jakarta')->addDay()->toDateString()],
        };
    }

    /** @return array{0: string, 1: string} */
    private function monthRange(): array
    {
        $month = max(1, min(12, (int) $this->month));
        $year = max(2000, min(2100, (int) $this->year));
        $start = CarbonImmutable::create($year, $month, 1, 0, 0, 0, 'Asia/Jakarta');

        return [$start->toDateString(), $start->addMonth()->toDateString()];
    }
}
