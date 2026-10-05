<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

final class WorkQueue
{
    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return Builder<T>
     */
    public static function apply(Builder $query, string $queue): Builder
    {
        $today = today('Asia/Jakarta');

        return match ($queue) {
            'pickup_today' => $query->whereIn('status', ['dijadwalkan', 'menuju_lokasi', 'dijemput'])->where(function (Builder $dates) use ($today): void {
                $dates->whereDate('scheduled_date', $today)->orWhere(fn (Builder $fallback): Builder => $fallback->whereNull('scheduled_date')->whereDate('selected_date', $today));
            }),
            'pickup_overdue' => $query->whereIn('status', ['dijadwalkan', 'menuju_lokasi', 'dijemput'])->where(function (Builder $dates) use ($today): void {
                $dates->whereDate('scheduled_date', '<', $today)->orWhere(fn (Builder $fallback): Builder => $fallback->whereNull('scheduled_date')->whereDate('selected_date', '<', $today));
            }),
            'withdrawal_unassigned' => $query->where('status', 'disetujui')->whereNull('payer_id'),
            'withdrawal_expiring' => $query->whereIn('status', ['disetujui', 'siap_diambil'])->whereBetween('expires_at', [now('Asia/Jakarta'), now('Asia/Jakarta')->addDays(2)]),
            'grocery_pending' => $query->where('status', 'menunggu_verifikasi'),
            default => $query->whereRaw('1 = 0'),
        };
    }
}
