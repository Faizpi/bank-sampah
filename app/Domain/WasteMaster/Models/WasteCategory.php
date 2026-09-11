<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use Database\Factories\WasteCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class WasteCategory extends WasteMasterModel
{
    /** @use HasFactory<WasteCategoryFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    protected static function newFactory(): WasteCategoryFactory
    {
        return WasteCategoryFactory::new();
    }

    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'is_active' => 'boolean'];
    }

    /** @return HasMany<WasteType, $this> */
    public function wasteTypes(): HasMany
    {
        return $this->hasMany(WasteType::class, 'waste_category_id');
    }
}
