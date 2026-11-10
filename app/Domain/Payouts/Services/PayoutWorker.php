<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Ledger\Services\LedgerService;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

final class PayoutWorker
{
    public function run(int $limit = 10, int $budgetSeconds = 40): int
    {
        app(PayoutAccess::class)->requireConfigured();
        $deadline = microtime(true) + max(1, $budgetSeconds);
        $rows = SandboxPayout::query()->where('next_check_at', '<=', now())
            ->where(function ($q): void {
                $q->whereIn('status', ['queued', 'pending', 'dispatching'])->orWhere(function ($q): void {
                    $q->where('status', 'uncertain')->whereNotNull('provider_id');
                })->orWhereExists(fn ($events) => $events->selectRaw('1')->from('sandbox_payout_events')->whereColumn('sandbox_payout_events.sandbox_payout_id', 'sandbox_payouts.id')->whereNull('processed_at'));
            })->orderBy('next_check_at')->orderBy('id')->limit(max(1, min(25, $limit)))->pluck('id');
        $count = 0;
        foreach ($rows as $id) {
            if (microtime(true) >= $deadline) {
                break;
            }
            if ($this->process((int) $id)) {
                $count++;
            }
        }

        return $count;
    }

    public function process(int $id, bool $recoverSubmission = false): bool
    {
        app(PayoutAccess::class)->requireConfigured();
        $ownerId = SandboxPayout::query()->findOrFail($id)->customer_id;
        $row = DB::transaction(function () use ($id, $ownerId, $recoverSubmission): ?SandboxPayout {
            $owner = User::query()->whereKey($ownerId)->lockForUpdate()->first();
            $row = SandboxPayout::query()->lockForUpdate()->findOrFail($id);
            if ($row->lease !== null) {
                if ($row->leased_at !== null && $row->leased_at->greaterThan(now()->subMinutes(2))) {
                    return null;
                }
                if ($row->provider_id === null) {
                    $row->forceFill(['status' => 'uncertain', 'failure_code' => 'STALE_SUBMISSION', 'next_check_at' => null, 'lease' => null, 'leased_at' => null])->save();
                    app(PayoutService::class)->notify($row);

                    return null;
                }
            }
            if ($row->isTerminal() && ! DB::table('sandbox_payout_events')->where('sandbox_payout_id', $row->id)->whereNull('processed_at')->exists()) {
                return null;
            }
            $recoverable = $recoverSubmission && $row->status === 'uncertain' && $row->updated_at->lessThanOrEqualTo(now()->subMinute());
            if ($row->provider_id === null && $row->status !== 'queued' && ! $recoverable) {
                return null;
            }
            if ($row->provider_id === null && ! $recoverable && ($owner === null || $owner->status->value !== 'aktif' || ! $owner->hasRole('warga'))) {
                app(LedgerService::class)->releaseHold(app(PayoutIntegrity::class)->hold($row));
                $row->forceFill(['status' => 'failed', 'failure_code' => 'ACCOUNT_UNAVAILABLE', 'next_check_at' => null])->save();
                app(PayoutService::class)->notify($row);

                return null;
            }
            $row->forceFill(['lease' => (string) Str::uuid(), 'leased_at' => now(), 'attempts' => $row->attempts + 1, 'status' => $row->provider_id === null ? 'dispatching' : $row->status])->save();
            if ($recoverable) {
                app(AuditLogger::class)->record(null, 'payout.recovery_requested', $row, [], ['environment' => 'test', 'attempt' => $row->attempts], (string) Str::uuid());
            }

            return $row;
        }, 5);
        if ($row === null) {
            return false;
        }
        $eventBoundary = (int) DB::table('sandbox_payout_events')->where('sandbox_payout_id', $row->id)->max('id');
        try {
            $api = app(XenditPayoutApi::class);
            $result = $row->provider_id === null ? $api->create($row) : $api->find($row->provider_id);
            app(PayoutSettlement::class)->apply($row->id, $result);
            DB::table('sandbox_payout_events')->where('sandbox_payout_id', $row->id)->where('id', '<=', $eventBoundary)->whereNull('processed_at')->update(['processed_at' => now()]);

            return true;
        } catch (Throwable $error) {
            DB::transaction(function () use ($row, $error): void {
                $current = SandboxPayout::query()->lockForUpdate()->findOrFail($row->id);
                if ($current->lease !== $row->lease) {
                    return;
                }
                $code = $error instanceof PayoutApiFailure ? $error->codeName : 'payout.response_unconfirmed';
                $current->forceFill(['status' => $current->isTerminal() ? $current->status : 'uncertain', 'failure_code' => $code,
                    'lease' => null, 'leased_at' => null, 'next_check_at' => $current->provider_id === null ? null : now()->addMinutes(5)])->save();
                app(PayoutService::class)->notify($current);
            }, 5);

            return false;
        }
    }
}
