<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use Database\Factories\WasteConditionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class WasteCondition extends WasteMasterModel
{
    /** @use HasFactory<WasteConditionFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'description', 'sort_order', 'is_active'];

    protected static function newFactory(): WasteConditionFactory
    {
        return WasteConditionFactory::new();
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return BelongsToMany<WasteType, $this> */
    public function wasteTypes(): BelongsToMany
    {
        return $this->belongsToMany(WasteType::class, 'waste_type_conditions', 'waste_condition_id', 'waste_type_id');
    }
}
