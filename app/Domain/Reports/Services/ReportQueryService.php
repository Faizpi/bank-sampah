<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Reports\Enums\ReportType;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Models\User;
use BackedEnum;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\LazyCollection;
use Illuminate\Validation\ValidationException;

/**
 * @phpstan-type ReportModel Deposit|WithdrawalRequest|GroceryRedemption|PickupRequest
 * @phpstan-type ReportBuilder Builder<Deposit>|Builder<WithdrawalRequest>|Builder<GroceryRedemption>|Builder<PickupRequest>
 * @phpstan-type ReportModelCollection EloquentCollection<int, Model>
 */
final readonly class ReportQueryService
{
    /** Interactive report records are intentionally capped to keep display requests bounded. */
    private const RECORD_LIMIT = 100;

    /** Export streams fetch this many models per database round trip without materializing the full report. */
    private const STREAM_CHUNK_SIZE = 100;

    /** @var list<string> */
    private const FILTERS = ['start', 'end', 'rt_id', 'service_area_id', 'status', 'waste_type_id', 'search'];

    /** @var list<string> */
    private const SORTS = ['occurred_at', 'deposit_number', 'request_number', 'created_at', 'total_weight_kg', 'total_value', 'amount', 'value_snapshot', 'difference'];

    /** @var list<string> */
    private const COLUMNS = ['deposit_number', 'occurred_at', 'customer_id', 'status', 'total_weight_kg', 'total_value'];

    /** @var array<string, list<string>> */
    private const REPORT_COLUMNS = [
        'deposits' => ['deposit_number', 'occurred_at', 'customer_id', 'status', 'total_weight_kg', 'total_value'],
        'withdrawals' => ['request_number', 'paid_at', 'customer_id', 'status', 'amount'],
        'groceries' => ['request_number', 'handed_over_at', 'customer_id', 'status', 'value_snapshot'],
        'pickups' => ['request_number', 'completed_at', 'customer_id', 'service_area_id', 'status'],
        'participation' => ['occurred_at', 'customer_id', 'status', 'total_weight_kg', 'total_value'],
    ];

    /** @var array<string, list<array{key: string, label: string, format: string}>> */
    private const SUMMARY_CONTRACT = [
        'deposits' => [
            ['key' => 'subject_count', 'label' => 'Nasabah', 'format' => 'count'],
            ['key' => 'deposit_count', 'label' => 'Setoran', 'format' => 'count'],
            ['key' => 'total_weight_kg', 'label' => 'Total Berat', 'format' => 'weight'],
            ['key' => 'total_value', 'label' => 'Total Nilai', 'format' => 'currency'],
            ['key' => 'plastic_weight_kg', 'label' => 'Plastik', 'format' => 'weight'],
        ],
        'withdrawals' => [
            ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
            ['key' => 'withdrawal_count', 'label' => 'Pencairan', 'format' => 'count'],
            ['key' => 'total_amount', 'label' => 'Total Pencairan', 'format' => 'currency'],
        ],
        'groceries' => [
            ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
            ['key' => 'redemption_count', 'label' => 'Penukaran Sembako', 'format' => 'count'],
            ['key' => 'total_redeemed_value', 'label' => 'Total Nilai Sembako', 'format' => 'currency'],
        ],
        'pickups' => [
            ['key' => 'customer_count', 'label' => 'Nasabah', 'format' => 'count'],
            ['key' => 'pickup_count', 'label' => 'Penjemputan', 'format' => 'count'],
            ['key' => 'estimated_weight_kg', 'label' => 'Estimasi Berat', 'format' => 'weight'],
        ],
        'participation' => [
            ['key' => 'participant_count', 'label' => 'Peserta', 'format' => 'count'],
            ['key' => 'participation_count', 'label' => 'Partisipasi', 'format' => 'count'],
            ['key' => 'collected_weight_kg', 'label' => 'Berat Terkumpul', 'format' => 'weight'],
            ['key' => 'collected_value', 'label' => 'Nilai Terkumpul', 'format' => 'currency'],
        ],
    ];

    public function __construct(private PermissionChecker $permissions, private DepositMetricService $metricService) {}

    /** @return array{filters: list<string>, sorts: list<string>, columns: list<string>, report_types: list<string>, report_columns: array<string, list<string>>, summary: array<string, list<array{key: string, label: string, format: string}>>} */
    public function contract(): array
    {
        return ['filters' => self::FILTERS, 'sorts' => self::SORTS, 'columns' => self::COLUMNS, 'report_types' => array_map(static fn (ReportType $type): string => $type->value, ReportType::cases()), 'report_columns' => self::REPORT_COLUMNS, 'summary' => self::SUMMARY_CONTRACT];
    }

    /** @return list<array{key: string, label: string, format: string}> */
    public function summaryContract(string|ReportType $reportType): array
    {
        return self::SUMMARY_CONTRACT[$this->type($reportType)->value];
    }

    /** @return list<string> */
    public function columnsFor(string|ReportType $reportType): array
    {
        $type = $reportType instanceof ReportType ? $reportType : ReportType::tryFrom($reportType);
        if ($type === null) {
            throw ValidationException::withMessages(['report_type' => 'Jenis laporan tidak diizinkan.']);
        }

        return self::REPORT_COLUMNS[$type->value];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Model>
     */
    public function paginate(User $actor, array $filters, string $sort = 'occurred_at', string $direction = 'desc', int $perPage = 25, string|ReportType $reportType = ReportType::Deposits): LengthAwarePaginator
    {
        $this->authorize($actor, 'report.view');
        $type = $this->type($reportType);
        $this->validateFilters($filters, $sort, $direction, $perPage, $type);

        $paginator = $this->orderedQuery($actor, $type, $filters, $sort, $direction)->paginate($perPage);
        $records = new SupportCollection($this->modelList($paginator->getCollection()));

        return new LengthAwarePaginator($records, $paginator->total(), $paginator->perPage(), $paginator->currentPage(), $paginator->getOptions());
    }

    /**
     * Deposit aggregation keeps its established metric keys because existing consumers depend on them.
     * Other report types use the explicit keys returned by summary().
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    public function aggregate(User $actor, array $filters, string|ReportType $reportType = ReportType::Deposits): array
    {
        $type = $this->type($reportType);
        if ($type !== ReportType::Deposits) {
            return $this->summary($actor, $type, $filters);
        }
        $this->authorize($actor, 'report.view');
        $this->validateFilters($filters, 'occurred_at', 'desc', 25, $type);
        $deposits = $this->depositQuery($actor, $filters)->with(['items.wasteType', 'correction'])->get();

        return $this->metricService->calculate(new EloquentCollection($deposits->all()));
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return ReportBuilder
     */
    public function query(User $actor, array $filters, string|ReportType $reportType = ReportType::Deposits): Builder
    {
        $this->authorize($actor, 'report.view');
        $type = $this->type($reportType);
        $this->validateFilters($filters, 'occurred_at', 'desc', 25, $type);

        return $this->scopedQuery($actor, $type, $filters);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return ReportModelCollection
     */
    public function records(User $actor, string|ReportType $reportType, array $filters, string $sort = 'occurred_at', string $direction = 'desc'): EloquentCollection
    {
        $this->authorize($actor, 'report.view');
        $type = $this->type($reportType);
        $this->validateFilters($filters, $sort, $direction, self::RECORD_LIMIT, $type);

        /** @var EloquentCollection<int, Model> $records */
        $records = $this->orderedQuery($actor, $type, $filters, $sort, $direction)->limit(self::RECORD_LIMIT)->get();

        return $records;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LazyCollection<int, Model>
     */
    public function streamRecords(User $actor, string|ReportType $reportType, array $filters, string $sort = 'occurred_at', string $direction = 'desc'): LazyCollection
    {
        $this->authorize($actor, 'report.view');
        $type = $this->type($reportType);
        $this->validateFilters($filters, $sort, $direction, self::RECORD_LIMIT, $type);

        /** @var LazyCollection<int, Model> $records */
        $records = $this->orderedQuery($actor, $type, $filters, $sort, $direction)->lazy(self::STREAM_CHUNK_SIZE);

        return $records;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return list<array{reference: string, date: string, subject_id: string, status: string, amount: int|string}>
     */
    public function displayRows(User $actor, string|ReportType $reportType, array $filters, string $sort = 'occurred_at', string $direction = 'desc'): array
    {
        $type = $this->type($reportType);
        $rows = [];
        foreach ($this->records($actor, $type, $filters, $sort, $direction) as $record) {
            $rows[] = [
                'reference' => $this->displayReference($record, $type),
                'date' => $this->displayDate($record, $type),
                'subject_id' => (string) ($record->getAttribute('customer_id') ?? ''),
                'status' => $this->displayValue($record->getAttribute('status')),
                'amount' => $this->displayAmount($record, $type),
            ];
        }

        return $rows;
    }

    /**
     * Summary metrics cover every record matching the selected scope and filters. The interactive rows remain capped at RECORD_LIMIT separately.
     *
     * @param  array<string, mixed>  $filters
     * @return array<string, int|string>
     */
    public function summary(User $actor, string|ReportType $reportType, array $filters): array
    {
        $type = $this->type($reportType);
        if ($type === ReportType::Deposits) {
            return $this->aggregate($actor, $filters);
        }
        $records = $this->query($actor, $filters, $type)->get();

        return match ($type) {
            ReportType::Withdrawals => [
                'customer_count' => $records->pluck('customer_id')->filter()->unique()->count(),
                'withdrawal_count' => $records->count(),
                'total_amount' => (int) $records->sum('amount'),
            ],
            ReportType::Groceries => [
                'customer_count' => $records->pluck('customer_id')->filter()->unique()->count(),
                'redemption_count' => $records->count(),
                'total_redeemed_value' => (int) $records->sum('value_snapshot'),
            ],
            ReportType::Pickups => [
                'customer_count' => $records->pluck('customer_id')->filter()->unique()->count(),
                'pickup_count' => $records->count(),
                'estimated_weight_kg' => number_format((float) $records->sum('estimated_weight_kg'), 3, '.', ''),
            ],
            ReportType::Participation => [
                'participant_count' => $records->pluck('customer_id')->filter()->unique()->count(),
                'participation_count' => $records->count(),
                'collected_weight_kg' => number_format((float) $records->sum('total_weight_kg'), 3, '.', ''),
                'collected_value' => $records->sum(static fn (Deposit $deposit): int => $deposit->effectiveTotalValue()),
            ],
        };
    }

    /**
     * @param  EloquentCollection<int, Deposit>  $deposits
     * @return array{subject_count: int, deposit_count: int, total_weight_kg: string, total_value: int, plastic_weight_kg: string}
     */
    public function metrics(EloquentCollection $deposits): array
    {
        $subjects = $deposits->pluck('customer_id')->unique()->count();
        $weight = 0.0;
        $value = 0;
        $plastic = 0.0;
        foreach ($deposits as $deposit) {
            $value += $deposit->effectiveTotalValue();
            foreach ($deposit->items as $item) {
                $itemWeight = (float) $item->weight_kg;
                $weight += $itemWeight;
                if ($item->wasteType?->is_plastic === true) {
                    $plastic += $itemWeight;
                }
            }
        }

        return ['subject_count' => $subjects, 'deposit_count' => $deposits->count(), 'total_weight_kg' => number_format($weight, 3, '.', ''), 'total_value' => $value, 'plastic_weight_kg' => number_format($plastic, 3, '.', '')];
    }

    /**
     * @param  EloquentCollection<int, Model>  $records
     * @return list<Model>
     */
    private function modelList(EloquentCollection $records): array
    {
        return array_values($records->all());
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Deposit>
     */
    private function depositQuery(User $actor, array $filters): Builder
    {
        $query = Deposit::query()->with('correction')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED]);
        $this->applyRecordScope($actor, ReportType::Deposits, $query);
        $this->applyFilters($query, ReportType::Deposits, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return ReportBuilder
     */
    private function scopedQuery(User $actor, ReportType $type, array $filters): Builder
    {
        $query = match ($type) {
            ReportType::Deposits, ReportType::Participation => Deposit::query()->with('correction')->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED]),
            ReportType::Withdrawals => WithdrawalRequest::query()->whereNotNull('paid_at'),
            ReportType::Groceries => GroceryRedemption::query()->whereNotNull('handed_over_at'),
            ReportType::Pickups => PickupRequest::query()->whereNotNull('completed_at'),
        };
        $this->applyRecordScope($actor, $type, $query);
        $this->applyFilters($query, $type, $filters);

        return $query;
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return ReportBuilder
     */
    private function orderedQuery(User $actor, ReportType $type, array $filters, string $sort, string $direction): Builder
    {
        return $this->scopedQuery($actor, $type, $filters)
            ->orderBy($this->sortColumn($type, $sort), $direction)
            ->orderBy('id', $direction);
    }

    /** @param ReportBuilder $query */
    private function applyRecordScope(User $actor, ReportType $type, Builder $query): void
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return;
        }
        $areaId = $actor->staffProfile?->service_area_id;
        if ($areaId !== null) {
            $query->where(function (Builder $scope) use ($actor, $type, $areaId): void {
                if (in_array($type, [ReportType::Deposits, ReportType::Participation], true)) {
                    $scope->where('staff_id', $actor->id)->orWhereHas('customer.customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey($areaId));
                } elseif ($type === ReportType::Pickups) {
                    $scope->where('service_area_id', $areaId)->orWhere('customer_id', $actor->id);
                } else {
                    $scope->where('customer_id', $actor->id)->orWhereHas('customer.customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey($areaId));
                }
            });

            return;
        }
        $query->where('customer_id', $actor->id);
    }

    /**
     * @param  ReportBuilder  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, ReportType $type, array $filters): void
    {
        $dateColumn = match ($type) {
            ReportType::Deposits, ReportType::Participation => 'occurred_at',
            ReportType::Withdrawals => 'paid_at',
            ReportType::Groceries => 'handed_over_at',
            ReportType::Pickups => 'completed_at',
        };
        $start = CarbonImmutable::parse((string) $filters['start'], 'Asia/Jakarta')->startOfDay();
        $end = CarbonImmutable::parse((string) $filters['end'], 'Asia/Jakarta')->startOfDay();
        $query->where($dateColumn, '>=', $start)->where($dateColumn, '<', $end);
        if (isset($filters['rt_id']) && $type === ReportType::Pickups) {
            $query->where('rt_id', (int) $filters['rt_id']);
        } elseif (isset($filters['rt_id'])) {
            $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', (int) $filters['rt_id']));
        }
        if (isset($filters['service_area_id'])) {
            if (in_array($type, [ReportType::Deposits, ReportType::Participation, ReportType::Withdrawals, ReportType::Groceries], true)) {
                $query->whereHas('customer.customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey((int) $filters['service_area_id']));
            } else {
                $query->where('service_area_id', (int) $filters['service_area_id']);
            }
        }
        if (isset($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['waste_type_id']) && in_array($type, [ReportType::Deposits, ReportType::Participation], true)) {
            $query->whereHas('items', static fn (Builder $items): Builder => $items->where('waste_type_id', (int) $filters['waste_type_id']));
        }
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $field = in_array($type, [ReportType::Deposits, ReportType::Participation], true) ? 'deposit_number' : 'request_number';
            $query->where($field, 'like', '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%');
        }
    }

    /** @param array<string, mixed> $filters */
    private function validateFilters(array $filters, string $sort, string $direction, int $perPage, ReportType $type): void
    {
        $unknown = array_diff(array_keys($filters), self::FILTERS);
        if ($unknown !== [] || ! isset($filters['start'], $filters['end']) || ! in_array($sort, self::SORTS, true) || ! in_array($direction, ['asc', 'desc'], true) || $perPage < 1 || $perPage > 100) {
            throw ValidationException::withMessages(['report' => 'Filter laporan tidak diizinkan.']);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['start']) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['end']) !== 1 || (string) $filters['start'] >= (string) $filters['end']) {
            throw ValidationException::withMessages(['period' => 'Periode laporan tidak valid.']);
        }
        $allowedStatuses = match ($type) {
            ReportType::Deposits, ReportType::Participation => [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED],
            ReportType::Withdrawals => ['sudah_dibayar'],
            ReportType::Groceries => ['selesai'],
            ReportType::Pickups => ['selesai'],
        };
        if (isset($filters['status']) && ! in_array($filters['status'], $allowedStatuses, true)) {
            throw ValidationException::withMessages(['status' => 'Status laporan tidak diizinkan.']);
        }
        foreach (['rt_id', 'service_area_id', 'waste_type_id'] as $key) {
            if (isset($filters[$key]) && (! is_int($filters[$key]) || $filters[$key] < 1)) {
                throw ValidationException::withMessages([$key => 'Filter laporan tidak valid.']);
            }
        }
    }

    private function displayReference(Model $record, ReportType $type): string
    {
        $column = in_array($type, [ReportType::Deposits, ReportType::Participation], true) ? 'deposit_number' : 'request_number';

        return $this->displayValue($record->getAttribute($column));
    }

    private function displayDate(Model $record, ReportType $type): string
    {
        $column = match ($type) {
            ReportType::Deposits, ReportType::Participation => 'occurred_at',
            ReportType::Withdrawals => 'paid_at',
            ReportType::Groceries => 'handed_over_at',
            ReportType::Pickups => 'completed_at',
        };

        $value = $record->getAttribute($column);
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone('Asia/Jakarta'))->format('Y-m-d H:i:s');
        }

        return $this->displayValue($value);
    }

    private function displayAmount(Model $record, ReportType $type): int|string
    {
        if ($record instanceof Deposit && in_array($type, [ReportType::Deposits, ReportType::Participation], true)) {
            return $record->effectiveTotalValue();
        }

        $column = match ($type) {
            ReportType::Deposits, ReportType::Participation => 'total_value',
            ReportType::Withdrawals => 'amount',
            ReportType::Groceries => 'value_snapshot',
            ReportType::Pickups => null,
        };

        return $column === null ? '' : (int) ($record->getAttribute($column) ?? 0);
    }

    private function displayValue(mixed $value): string
    {
        if ($value instanceof BackedEnum) {
            return (string) $value->value;
        }
        if (is_scalar($value) || $value === null) {
            return (string) $value;
        }

        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    private function sortColumn(ReportType $type, string $sort): string
    {
        return match ($type) {
            ReportType::Deposits, ReportType::Participation => in_array($sort, ['occurred_at', 'deposit_number', 'total_weight_kg', 'total_value'], true) ? $sort : 'occurred_at',
            ReportType::Withdrawals, ReportType::Groceries, ReportType::Pickups => in_array($sort, ['occurred_at', 'request_number', 'amount', 'value_snapshot', 'created_at'], true) ? ($sort === 'occurred_at' ? ($type === ReportType::Withdrawals ? 'paid_at' : ($type === ReportType::Groceries ? 'handed_over_at' : 'completed_at')) : $sort) : 'created_at',
        };
    }

    private function type(string|ReportType $value): ReportType
    {
        return $value instanceof ReportType ? $value : ReportType::tryFrom($value) ?? throw ValidationException::withMessages(['report_type' => 'Jenis laporan tidak diizinkan.']);
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission) || $actor->status !== UserStatus::Active) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap laporan.');
        }
    }
}
