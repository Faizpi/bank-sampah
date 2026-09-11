<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Models;

use App\Domain\CustomersRegions\Models\ServiceArea;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $service_area_id
 * @property CarbonImmutable $service_date
 * @property int|null $max_addresses
 * @property string|null $max_weight_kg
 * @property bool $is_active
 */
final class PickupCapacity extends Model
{
    protected $table = 'pickup_capacities';

    protected $fillable = ['service_area_id', 'service_date', 'max_addresses', 'max_weight_kg', 'vehicle_label', 'is_active'];

    protected function casts(): array
    {
        return ['service_date' => 'immutable_date', 'max_addresses' => 'integer', 'max_weight_kg' => 'decimal:3', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }
}
