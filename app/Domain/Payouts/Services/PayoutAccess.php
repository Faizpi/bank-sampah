<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PayoutAccess
{
    public static function configured(): bool
    {
        $database = DB::connection()->getDatabaseName();
        $isolated = (DB::getDriverName() === 'sqlite' && $database === ':memory:' && app()->environment('testing'))
            || (($database === config('payouts.database'))
                && (preg_match('/^bank_sampah_test(?:_[a-z0-9]+)*$/D', $database) === 1 || str_ends_with($database, '_sandbox')));

        return (bool) config('payouts.enabled') && $isolated
            && str_starts_with((string) config('payouts.secret_key'), 'xnd_development_')
            && strlen((string) config('payouts.webhook_token')) >= 16;
    }

    public function requireConfigured(): void
    {
        if (! self::configured()) {
            throw ValidationException::withMessages(['payout' => 'Pencairan simulasi belum tersedia. Gunakan lingkungan riset dan konfigurasi Xendit Test Mode yang benar.']);
        }
    }

    /** @return Builder<SandboxPayout> */
    public function visibleFor(User $actor): Builder
    {
        $actor = User::query()->findOrFail($actor->id);
        if ($actor->status->value !== 'aktif' || ! $actor->hasRole('warga', 'bendahara', 'admin')) {
            throw new AuthorizationException('Tidak berhak membuka pencairan simulasi.');
        }
        $query = SandboxPayout::query()->with('customer');
        if ($actor->hasRole('warga')) {
            return $query->where('customer_id', $actor->id);
        }
        if ($actor->hasRole('admin')) {
            return $query;
        }
        $areas = StaffServiceArea::query()->where('staff_profile_user_id', $actor->id)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('active_to')->orWhereDate('active_to', '>=', today()))
            ->whereHas('serviceArea', fn ($q) => $q->where('is_active', true))->select('service_area_id');

        return $query->whereIn('service_area_id', $areas);
    }
}
