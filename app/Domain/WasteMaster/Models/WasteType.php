<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use App\Domain\Platform\Models\Media;
use App\Domain\WasteMaster\Support\GuardedWasteTypeConditionsRelation;
use Database\Factories\WasteTypeFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class WasteType extends WasteMasterModel
{
    /** @use HasFactory<WasteTypeFactory> */
    use HasFactory;

    protected $fillable = [
        'waste_category_id',
        'waste_unit_id',
        'code',
        'name',
        'education_description',
        'sort_order',
        'is_plastic',
        'media_id',
        'is_active',
    ];

    protected static function newFactory(): WasteTypeFactory
    {
        return WasteTypeFactory::new();
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_plastic' => 'boolean', 'is_active' => 'boolean'];
    }

    /** @return BelongsTo<WasteCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class, 'waste_category_id');
    }

    /** @return BelongsTo<WasteUnit, $this> */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(WasteUnit::class, 'waste_unit_id');
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class);
    }

    /** @return GuardedWasteTypeConditionsRelation<WasteCondition, $this> */
    public function conditions(): GuardedWasteTypeConditionsRelation
    {
        return new GuardedWasteTypeConditionsRelation(
            WasteCondition::query(),
            $this,
            'waste_type_conditions',
            'waste_type_id',
            'waste_condition_id',
            'id',
            'id',
            'conditions',
        );
    }
}
