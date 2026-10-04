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
}
