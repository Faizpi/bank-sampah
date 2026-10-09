<?php

declare(strict_types=1);

namespace App\Domain\Statistics\Services;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/** All-village totals only: no names, balances, contact details, filters, or online-status claims. */
final class LandingStatistics
{
    public function activeOfficers(?int $rtId = null): int
    {
        $date = today()->toDateString();

        return User::query()->where('role', 'petugas')->where('status', 'aktif')
            ->whereHas('staffProfile', fn (Builder $profile): Builder => $profile
                ->where(fn (Builder $from): Builder => $from->whereNull('active_from')->orWhere('active_from', '<=', $date))
                ->where(fn (Builder $to): Builder => $to->whereNull('active_to')->orWhere('active_to', '>=', $date))
                ->whereHas('serviceAreas', fn (Builder $assignment): Builder => $assignment
                    ->where(fn (Builder $from): Builder => $from->whereNull('active_from')->orWhere('active_from', '<=', $date))
                    ->where(fn (Builder $to): Builder => $to->whereNull('active_to')->orWhere('active_to', '>=', $date))
                    ->whereHas('serviceArea', fn (Builder $area): Builder => $area->where('is_active', true)
                        ->when($rtId !== null, fn (Builder $area): Builder => $area->whereHas('rts', fn (Builder $rt): Builder => $rt->whereKey($rtId))))))
            ->count();
    }

    /** @return array{active_customers: int|null, active_officers: int|null, deposit_count: int|null, total_weight_kg: string|null, protected: bool} */
    public function summary(): array
    {
        $publication = StatisticPublication::query()->where('publication_key', 'public-dashboard')->first();
        $allowed = $publication === null ? ['active_customers', 'active_officers', 'deposit_count', 'total_weight_kg'] : $publication->metrics;
        if ($publication !== null && ! $publication->is_active) {
            $allowed = [];
        }
        $deposits = Deposit::query()->whereIn('status', [Deposit::STATUS_FINAL, Deposit::STATUS_CORRECTED])->where('occurred_at', '<=', now());
        $count = (clone $deposits)->count();
        $subjects = (clone $deposits)->distinct()->count('customer_id');
        $threshold = $publication === null ? (int) config('app.statistics_privacy_threshold', 5) : $publication->privacy_threshold;
        // Empty means genuinely zero; small non-empty groups never masquerade as zero.
        $safeTransactions = $count === 0 || $subjects >= $threshold;

        return [
            'active_customers' => in_array('active_customers', $allowed, true)
                ? User::query()->where('role', 'warga')->where('status', 'aktif')->whereHas('customerProfile')->count() : null,
            'active_officers' => in_array('active_officers', $allowed, true) ? $this->activeOfficers() : null,
            'deposit_count' => $safeTransactions && in_array('deposit_count', $allowed, true) ? $count : null,
            'total_weight_kg' => $safeTransactions && in_array('total_weight_kg', $allowed, true)
                ? number_format((float) (clone $deposits)->sum('total_weight_kg'), 3, '.', '') : null,
            'protected' => ! $safeTransactions || $allowed === [],
        ];
    }
}
