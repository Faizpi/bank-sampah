<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Groceries\Enums\GroceryStatus;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Notifications\Models\NotificationDeliveryFailure;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportExportService;
use App\Domain\Withdrawals\Enums\WithdrawalStatus;
use App\Domain\Withdrawals\Models\WithdrawalRequest;
use App\Domain\Withdrawals\Services\WithdrawalService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final readonly class ScheduledOperationsService
{
    public function __construct(
        private WithdrawalService $withdrawals,
        private GroceryService $groceries,
        private PickupService $pickups,
        private ReportExportService $exports,
        private AuditLogger $auditLogger,
    ) {}

    /** @return array{withdrawals: int, groceries: int, pickups: int, exports: int} */
    public function expireEligible(): array
    {
        $now = now();

        return [
            'withdrawals' => $this->expireWithdrawals($now),
            'groceries' => $this->expireGroceries($now),
            'pickups' => $this->expirePickups($now),
            'exports' => $this->expireExports($now),
        ];
    }

    /** @return array{idempotency_keys: int, notification_failures: int} */
    public function purgeExpiredOperationalRows(): array
    {
        $now = now();
        $correlationId = (string) Str::uuid();

        return [
            'idempotency_keys' => $this->purgeIdempotencyKeys($now, $correlationId),
            'notification_failures' => $this->purgeNotificationFailures($now, $correlationId),
        ];
    }

    private function expireWithdrawals(CarbonInterface $now): int
    {
        $ids = $this->ids(
            WithdrawalRequest::query()
                ->whereIn('status', [
                    WithdrawalStatus::PendingVerification->value,
                    WithdrawalStatus::Approved->value,
                    WithdrawalStatus::ReadyForPickup->value,
                ])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now),
        );

        $expired = 0;
        foreach ($ids as $id) {
            $expired += $this->expireWithdrawal($id, $now) ? 1 : 0;
        }

        return $expired;
    }

    private function expireGroceries(CarbonInterface $now): int
    {
        $ids = $this->ids(
            GroceryRedemption::query()
                ->whereIn('status', [
                    GroceryStatus::PendingVerification->value,
                    GroceryStatus::Approved->value,
                    GroceryStatus::Preparing->value,
                    GroceryStatus::ReadyForPickup->value,
                ])
                ->whereNotNull('expires_at')
                ->where('expires_at', '<=', $now),
        );

        $expired = 0;
        foreach ($ids as $id) {
            $expired += $this->expireGrocery($id, $now) ? 1 : 0;
        }

        return $expired;
    }

    private function expirePickups(CarbonInterface $now): int
    {
        $ids = $this->ids(
            PickupRequest::query()
                ->whereIn('status', [
                    PickupStatus::PendingReview->value,
                    PickupStatus::Accepted->value,
                    PickupStatus::Scheduled->value,
                ])
                ->where(static function (Builder $query) use ($now): void {
                    $query->whereDate('scheduled_date', '<', $now->toDateString())
                        ->orWhere(static function (Builder $query) use ($now): void {
                            $query->whereNull('scheduled_date')->whereDate('selected_date', '<', $now->toDateString());
                        });
                }),
        );

        $expired = 0;
        foreach ($ids as $id) {
            $expired += $this->expirePickup($id, $now) ? 1 : 0;
        }

        return $expired;
    }

    private function expireExports(CarbonInterface $now): int
    {
        $ids = $this->ids(
            ReportExport::query()
                ->where('status', '!=', ReportExportStatus::Expired->value)
                ->where('expires_at', '<=', $now),
        );
        $correlationId = (string) Str::uuid();
        $expired = 0;

        foreach ($ids as $id) {
            $expired += $this->expireExport($id, $now, $correlationId) ? 1 : 0;
        }

        return $expired;
    }

    private function expireWithdrawal(int $id, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($id, $now): bool {
            $withdrawal = WithdrawalRequest::query()->lockForUpdate()->find($id);
            if (! $withdrawal instanceof WithdrawalRequest || ! $this->isExpiredWithdrawal($withdrawal, $now)) {
                return false;
            }

            $this->withdrawals->expire($withdrawal);

            return true;
        }, 3);
    }

    private function expireGrocery(int $id, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($id, $now): bool {
            $redemption = GroceryRedemption::query()->lockForUpdate()->find($id);
            if (! $redemption instanceof GroceryRedemption || ! $this->isExpiredGrocery($redemption, $now)) {
                return false;
            }

            $this->groceries->expire($redemption);

            return true;
        }, 3);
    }

    private function expirePickup(int $id, CarbonInterface $now): bool
    {
        return DB::transaction(function () use ($id, $now): bool {
            $pickup = PickupRequest::query()->lockForUpdate()->find($id);
            if (! $pickup instanceof PickupRequest || ! $this->isExpiredPickup($pickup, $now)) {
                return false;
            }

            $this->pickups->expire($pickup);

            return true;
        }, 3);
    }

    private function expireExport(int $id, CarbonInterface $now, string $correlationId): bool
    {
        return DB::transaction(function () use ($id, $now, $correlationId): bool {
            $export = ReportExport::query()->lockForUpdate()->find($id);
            if (! $export instanceof ReportExport || ! $this->isExpiredExport($export, $now)) {
                return false;
            }

            $oldStatus = $export->status;
            $expired = $this->exports->expire($export);
            $this->auditLogger->record(
                null,
                'report.export.expired',
                $expired,
                ['status' => $oldStatus->value],
                ['status' => ReportExportStatus::Expired->value],
                $correlationId,
            );

            return true;
        }, 3);
    }

    private function purgeIdempotencyKeys(CarbonInterface $now, string $correlationId): int
    {
        $ids = $this->ids(
            IdempotencyKey::query()->where(static function (Builder $query) use ($now): void {
                $query->whereNull('expires_at')->orWhere('expires_at', '<=', $now);
            }),
        );
        $purged = 0;

        foreach ($ids as $id) {
            $purged += $this->purgeIdempotencyKey($id, $now, $correlationId) ? 1 : 0;
        }

        return $purged;
    }

    private function purgeNotificationFailures(CarbonInterface $now, string $correlationId): int
    {
        $staleBefore = $now->copy()->subHours($this->notificationFailureRetentionHours());
        $ids = $this->ids(
            NotificationDeliveryFailure::query()
                ->where('last_attempted_at', '<=', $staleBefore)
                ->where(static function (Builder $query) use ($now): void {
                    $query->whereNull('retry_after')->orWhere('retry_after', '<=', $now);
                }),
        );
        $purged = 0;

        foreach ($ids as $id) {
            $purged += $this->purgeNotificationFailure($id, $staleBefore, $now, $correlationId) ? 1 : 0;
        }

        return $purged;
    }

    private function purgeIdempotencyKey(int $id, CarbonInterface $now, string $correlationId): bool
    {
        return DB::transaction(function () use ($id, $now, $correlationId): bool {
            $key = IdempotencyKey::query()->lockForUpdate()->find($id);
            if (! $key instanceof IdempotencyKey || ($key->expires_at !== null && $key->expires_at->isAfter($now))) {
                return false;
            }

            $this->auditLogger->record(null, 'operations.idempotency_key.purged', $key, [], ['retention' => 'expired'], $correlationId);
            $key->delete();

            return true;
        }, 3);
    }

    private function purgeNotificationFailure(int $id, CarbonInterface $staleBefore, CarbonInterface $now, string $correlationId): bool
    {
        return DB::transaction(function () use ($id, $staleBefore, $now, $correlationId): bool {
            $failure = NotificationDeliveryFailure::query()->lockForUpdate()->find($id);
            if (! $failure instanceof NotificationDeliveryFailure || $failure->last_attempted_at->isAfter($staleBefore) || ($failure->retry_after !== null && $failure->retry_after->isAfter($now))) {
                return false;
            }

            $this->auditLogger->record(null, 'operations.notification_failure.purged', $failure, [], ['retention' => 'stale'], $correlationId);
            $failure->delete();

            return true;
        }, 3);
    }

    private function isExpiredWithdrawal(WithdrawalRequest $withdrawal, CarbonInterface $now): bool
    {
        return ! $withdrawal->status->isTerminal()
            && $withdrawal->expires_at !== null
            && $withdrawal->expires_at->lessThanOrEqualTo($now);
    }

    private function isExpiredGrocery(GroceryRedemption $redemption, CarbonInterface $now): bool
    {
        return ! $redemption->status->isTerminal()
            && $redemption->expires_at !== null
            && $redemption->expires_at->lessThanOrEqualTo($now);
    }

    private function isExpiredPickup(PickupRequest $pickup, CarbonInterface $now): bool
    {
        return ! $pickup->isTerminal()
            && ($pickup->scheduled_date ?? $pickup->selected_date)->isBefore($now->copy()->startOfDay());
    }

    private function isExpiredExport(ReportExport $export, CarbonInterface $now): bool
    {
        return $export->status !== ReportExportStatus::Expired && $export->expires_at->lessThanOrEqualTo($now);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Builder<TModel>  $query
     * @return list<int>
     */
    private function ids(Builder $query): array
    {
        return array_map(static fn (mixed $id): int => (int) $id, $query->orderBy('id')->limit($this->batchSize())->pluck('id')->all());
    }

    private function batchSize(): int
    {
        return min(1_000, max(1, (int) config('operations.scheduler.batch_size', 50)));
    }

    private function notificationFailureRetentionHours(): int
    {
        return min(8_760, max(1, (int) config('operations.retention.notification_failure_hours', 168)));
    }
}
