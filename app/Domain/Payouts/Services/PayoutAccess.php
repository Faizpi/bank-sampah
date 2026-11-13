<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

final class PayoutAccess
{
    public static function previewOnly(): bool
    {
        return app()->environment('local') && (bool) config('payouts.preview_only');
    }

    public static function canViewForm(): bool
    {
        return self::configured() || (self::previewOnly()
            && Schema::hasTable('sandbox_payouts') && Schema::hasTable('sandbox_payout_events'));
    }

    public static function configured(): bool
    {
        $database = DB::connection()->getDatabaseName();
        // The owner may designate the existing hosting database as simulation-only.
        // Require its exact name, not a naming convention; live Xendit keys remain rejected.
        $databaseConfirmed = (DB::getDriverName() === 'sqlite' && $database === ':memory:' && app()->environment('testing'))
            || ($database !== '' && $database === config('payouts.database'));

        return (bool) config('payouts.enabled') && ! self::previewOnly() && $databaseConfirmed
            && str_starts_with((string) config('payouts.secret_key'), 'xnd_development_')
            && strlen((string) config('payouts.webhook_token')) >= 16
            && Schema::hasTable('sandbox_payouts') && Schema::hasTable('sandbox_payout_events');
    }

    public function requireConfigured(): void
    {
        if (! self::configured()) {
            throw ValidationException::withMessages(['payout' => 'Pencairan digital belum tersedia. Gunakan pencairan tunai.']);
        }
    }

    /** @return Builder<SandboxPayout> */
    public function visibleFor(User $actor): Builder
    {
        abort_unless(Schema::hasTable('sandbox_payouts'), 404);
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
