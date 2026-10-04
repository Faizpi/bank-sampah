<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use App\Domain\CustomersRegions\Support\GuardedServiceAreaRtsRelation;
use App\Domain\Identity\Models\StaffProfile;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class ServiceArea extends RegionModel
{
    public $timestamps = false;

    protected $fillable = ['name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return GuardedServiceAreaRtsRelation<Rt, $this> */
    public function rts(): GuardedServiceAreaRtsRelation
    {
        return new GuardedServiceAreaRtsRelation(
            Rt::query(),
            $this,
            'service_area_rt',
            'service_area_id',
            'rt_id',
            'id',
            'id',
            'rts',
        );
    }

    /** @return HasMany<StaffProfile, $this> */
    public function staffProfiles(): HasMany
    {
        return $this->hasMany(StaffProfile::class);
    }
}
