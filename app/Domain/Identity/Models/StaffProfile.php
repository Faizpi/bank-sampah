<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\StaffProfileFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $user_id
 * @property int|null $service_area_id
 * @property CarbonImmutable|null $active_from
 * @property CarbonImmutable|null $active_to
 */
final class StaffProfile extends Model
{
    /** @use HasFactory<StaffProfileFactory> */
    use HasFactory;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $fillable = ['user_id', 'staff_number', 'service_area_id', 'active_from', 'active_to'];

    protected static function newFactory(): StaffProfileFactory
    {
        return StaffProfileFactory::new();
    }

    protected function casts(): array
    {
        return ['active_from' => 'immutable_date', 'active_to' => 'immutable_date'];
    }

    protected static function booted(): void
    {
        self::saved(static function (self $profile): void {
            if ($profile->service_area_id !== null) {
                $profile->serviceAreas()->firstOrCreate(
                    ['service_area_id' => $profile->service_area_id],
                    ['active_from' => $profile->active_from?->toDateString(), 'active_to' => $profile->active_to?->toDateString()],
                );
            }
        });
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /** @return HasMany<StaffServiceArea, $this> */
    public function serviceAreas(): HasMany
    {
        return $this->hasMany(StaffServiceArea::class, 'staff_profile_user_id', 'user_id');
    }
}
