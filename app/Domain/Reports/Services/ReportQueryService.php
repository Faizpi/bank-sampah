<?php

declare(strict_types=1);

namespace App\Domain\Reports\Services;

use App\Authorization\PermissionChecker;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Reports\Enums\ReportType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Validation\ValidationException;

final readonly class ReportQueryService
{
    /** @var list<string> */
    private const FILTERS = ['start', 'end', 'rt_id', 'service_area_id', 'status', 'waste_type_id', 'search'];

    /** @var list<string> */
    private const SORTS = ['occurred_at', 'deposit_number', 'total_weight_kg', 'total_value'];

    /** @var list<string> */
    private const COLUMNS = ['deposit_number', 'occurred_at', 'customer_id', 'status', 'total_weight_kg', 'total_value'];

    public function __construct(private PermissionChecker $permissions, private DepositMetricService $metricService) {}

    /** @return array{filters: list<string>, sorts: list<string>, columns: list<string>, report_types: list<string>} */
    public function contract(): array
    {
        return ['filters' => self::FILTERS, 'sorts' => self::SORTS, 'columns' => self::COLUMNS, 'report_types' => array_map(static fn (ReportType $type): string => $type->value, ReportType::cases())];
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return LengthAwarePaginator<int, Deposit>
     */
    public function paginate(User $actor, array $filters, string $sort = 'occurred_at', string $direction = 'desc', int $perPage = 25): LengthAwarePaginator
    {
        $this->authorize($actor, 'report.view');
        $this->validateFilters($filters, $sort, $direction, $perPage);

        return $this->scopedDeposits($actor, $filters)->orderBy($sort, $direction)->orderBy('id', $direction)->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array{subject_count: int, deposit_count: int, total_weight_kg: string, total_value: int, plastic_weight_kg: string}
     */
    public function aggregate(User $actor, array $filters): array
    {
        $this->authorize($actor, 'report.view');
        $this->validateFilters($filters, 'occurred_at', 'desc', 25);
        $deposits = $this->scopedDeposits($actor, $filters)->with('items')->get();

        return $this->metricService->calculate($deposits);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<Deposit>
     */
    public function query(User $actor, array $filters): Builder
    {
        $this->authorize($actor, 'report.view');
        $this->validateFilters($filters, 'occurred_at', 'desc', 25);

        return $this->scopedDeposits($actor, $filters);
    }

    /**
     * @param  Collection<int, Deposit>  $deposits
     * @return array{subject_count: int, deposit_count: int, total_weight_kg: string, total_value: int, plastic_weight_kg: string}
     */
    public function metrics(Collection $deposits): array
    {
        $subjects = $deposits->pluck('customer_id')->unique()->count();
        $weight = 0.0;
        $value = 0;
        $plastic = 0.0;
        foreach ($deposits as $deposit) {
            $value += (int) $deposit->total_value;
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
     * @param  array<string, mixed>  $filters
     * @return Builder<Deposit>
     */
    private function scopedDeposits(User $actor, array $filters): Builder
    {
        $query = Deposit::query()->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED]);
        $this->applyRecordScope($actor, $query);
        $this->applyFilters($query, $filters);

        return $query;
    }

    /** @param Builder<Deposit> $query */
    private function applyRecordScope(User $actor, Builder $query): void
    {
        if ($this->permissions->allows($actor, 'user.view.all')) {
            return;
        }

        if ($this->permissions->allows($actor, 'report.view') && $actor->staffProfile !== null && $actor->staffProfile->service_area_id !== null) {
            $query->where(function (Builder $scope) use ($actor): void {
                $scope->where('staff_id', $actor->id)->orWhereHas('customer.customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey($actor->staffProfile->service_area_id));
            });

            return;
        }

        $query->where('customer_id', $actor->id);
    }

    /**
     * @param  Builder<Deposit>  $query
     * @param  array<string, mixed>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        $start = CarbonImmutable::parse((string) $filters['start'], 'Asia/Jakarta')->startOfDay();
        $end = CarbonImmutable::parse((string) $filters['end'], 'Asia/Jakarta')->startOfDay();
        $query->where('occurred_at', '>=', $start)->where('occurred_at', '<', $end);
        if (isset($filters['rt_id'])) {
            $query->whereHas('customer.customerProfile', static fn (Builder $profile): Builder => $profile->where('rt_id', (int) $filters['rt_id']));
        }
        if (isset($filters['service_area_id'])) {
            $query->whereHas('customer.customerProfile.rt.serviceAreas', static fn (Builder $area): Builder => $area->whereKey((int) $filters['service_area_id']));
        }
        if (isset($filters['status'])) {
            $query->where('status', (string) $filters['status']);
        }
        if (isset($filters['waste_type_id'])) {
            $query->whereHas('items', static fn (Builder $items): Builder => $items->where('waste_type_id', (int) $filters['waste_type_id']));
        }
        if (isset($filters['search']) && trim((string) $filters['search']) !== '') {
            $query->where('deposit_number', 'like', '%'.addcslashes(trim((string) $filters['search']), '%_\\').'%');
        }
    }

    /** @param array<string, mixed> $filters */
    private function validateFilters(array $filters, string $sort, string $direction, int $perPage): void
    {
        $unknown = array_diff(array_keys($filters), self::FILTERS);
        if ($unknown !== [] || ! isset($filters['start'], $filters['end']) || ! in_array($sort, self::SORTS, true) || ! in_array($direction, ['asc', 'desc'], true) || $perPage < 1 || $perPage > 100) {
            throw ValidationException::withMessages(['report' => 'Filter laporan tidak diizinkan.']);
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['start']) !== 1 || preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $filters['end']) !== 1 || (string) $filters['start'] >= (string) $filters['end']) {
            throw ValidationException::withMessages(['period' => 'Periode laporan tidak valid.']);
        }
        if (isset($filters['status']) && ! in_array($filters['status'], [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED], true)) {
            throw ValidationException::withMessages(['status' => 'Status laporan tidak diizinkan.']);
        }
        foreach (['rt_id', 'service_area_id', 'waste_type_id'] as $key) {
            if (isset($filters[$key]) && (! is_int($filters[$key]) || $filters[$key] < 1)) {
                throw ValidationException::withMessages([$key => 'Filter laporan tidak valid.']);
            }
        }
    }

    private function authorize(User $actor, string $permission): void
    {
        if (! $this->permissions->allows($actor, $permission) || $actor->status !== UserStatus::Active) {
            throw new AuthorizationException('Anda tidak memiliki akses terhadap laporan.');
        }
    }
}
