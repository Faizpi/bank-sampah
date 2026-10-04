<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use App\Domain\CustomersRegions\Support\GuardedServiceAreaRtsRelation;
use App\Domain\Identity\Models\CustomerProfile;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Rt extends RegionModel
{
    public $timestamps = false;

    protected $table = 'rt';

    protected $fillable = ['rw_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Rw, $this> */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /** @return GuardedServiceAreaRtsRelation<ServiceArea, $this> */
    public function serviceAreas(): GuardedServiceAreaRtsRelation
    {
        return new GuardedServiceAreaRtsRelation(
            ServiceArea::query(),
            $this,
            'service_area_rt',
            'rt_id',
            'service_area_id',
            'id',
            'id',
            'serviceAreas',
        );
    }

    /** @return HasMany<CustomerProfile, $this> */
    public function customerProfiles(): HasMany
    {
        return $this->hasMany(CustomerProfile::class);
    }
}
