<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Models;

use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class PickupItem extends Model
{
    protected $fillable = ['pickup_request_id', 'waste_type_id', 'estimated_weight_kg', 'estimated_quantity'];

    protected function casts(): array
    {
        return ['estimated_weight_kg' => 'decimal:3', 'estimated_quantity' => 'integer'];
    }

    /** @return BelongsTo<PickupRequest, $this> */
    public function pickupRequest(): BelongsTo
    {
        return $this->belongsTo(PickupRequest::class);
    }

    /** @return BelongsTo<WasteType, $this> */
    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}
