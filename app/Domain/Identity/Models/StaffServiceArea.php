<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\CustomersRegions\Models\ServiceArea;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $staff_profile_user_id
 * @property int $service_area_id
 * @property CarbonImmutable|null $active_from
 * @property CarbonImmutable|null $active_to
 */
final class StaffServiceArea extends Model
{
    protected $fillable = ['staff_profile_user_id', 'service_area_id', 'active_from', 'active_to'];

    protected function casts(): array
    {
        return ['active_from' => 'immutable_date', 'active_to' => 'immutable_date'];
    }

    /** @return BelongsTo<StaffProfile, $this> */
    public function staffProfile(): BelongsTo
    {
        return $this->belongsTo(StaffProfile::class, 'staff_profile_user_id', 'user_id');
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    public function isEffectiveOn(CarbonImmutable $date): bool
    {
        return ($this->active_from === null || $this->active_from->lessThanOrEqualTo($date))
            && ($this->active_to === null || $this->active_to->greaterThanOrEqualTo($date));
    }
}
