<?php

declare(strict_types=1);

namespace App\Domain\Programs\Models;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class TargetScope extends Model
{
    protected $fillable = ['collection_target_id', 'waste_type_id', 'waste_category_id', 'rt_id'];

    /** @return BelongsTo<WasteType, $this> */
    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }

    /** @return BelongsTo<WasteCategory, $this> */
    public function wasteCategory(): BelongsTo
    {
        return $this->belongsTo(WasteCategory::class);
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }
}
