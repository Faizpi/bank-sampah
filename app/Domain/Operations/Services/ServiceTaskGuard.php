<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class ServiceTaskGuard
{
    public function check(User $actor, UserRole $role, ?int $areaId): User
    {
        $current = User::query()->whereKey($actor->id)->lockForUpdate()->first();
        $area = $areaId === null ? null : ServiceArea::query()->whereKey($areaId)->lockForUpdate()->first();
        $assignment = StaffServiceArea::query()->where('staff_profile_user_id', $actor->id)->where('service_area_id', $areaId)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('active_to')->orWhereDate('active_to', '>=', today()))->lockForUpdate()->first();
        if ($current === null || $current->role !== $role || $current->status !== UserStatus::Active || $area?->is_active !== true || $assignment === null) {
            throw new AuthorizationException('Tugas memerlukan peran dan penugasan area yang masih aktif.');
        }

        return $current;
    }

    public function inArea(User $actor, ?int $areaId): bool
    {
        return $areaId !== null && StaffServiceArea::query()
            ->where('staff_profile_user_id', $actor->id)->where('service_area_id', $areaId)
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('active_to')->orWhereDate('active_to', '>=', today()))
            ->whereHas('serviceArea', fn ($q) => $q->where('is_active', true))->exists();
    }

    public function forCustomer(User $actor, User $customer): void
    {
        $current = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        $owner = User::query()->whereKey($customer->id)->lockForUpdate()->firstOrFail();
        $profile = $owner->customerProfile()->lockForUpdate()->first();
        if ($current->role !== UserRole::Officer || $current->status !== UserStatus::Active || $owner->role !== UserRole::Citizen || $owner->status !== UserStatus::Active || $profile === null) {
            throw new AuthorizationException('Setoran hanya oleh Petugas aktif untuk Warga aktif.');
        }
        $rt = Rt::query()->whereKey($profile->rt_id)->lockForUpdate()->firstOrFail();
        $rw = Rw::query()->whereKey($rt->rw_id)->lockForUpdate()->firstOrFail();
        $dusun = Dusun::query()->whereKey($rw->dusun_id)->lockForUpdate()->firstOrFail();
        if (! $rt->is_active || ! $rw->is_active || ! $dusun->is_active) {
            throw new AuthorizationException('Wilayah warga tidak aktif.');
        }
        $areaIds = DB::table('service_area_rt')->where('rt_id', $rt->id)->lockForUpdate()->pluck('service_area_id');
        $areas = ServiceArea::query()->whereIn('id', $areaIds)->where('is_active', true)->orderBy('id')->lockForUpdate()->get();
        $assignment = StaffServiceArea::query()->where('staff_profile_user_id', $actor->id)->whereIn('service_area_id', $areas->modelKeys())
            ->where(fn ($q) => $q->whereNull('active_from')->orWhereDate('active_from', '<=', today()))
            ->where(fn ($q) => $q->whereNull('active_to')->orWhereDate('active_to', '>=', today()))->lockForUpdate()->first();
        if ($assignment === null) {
            throw new AuthorizationException('Warga berada di luar area tugas Petugas yang aktif.');
        }
    }
}
