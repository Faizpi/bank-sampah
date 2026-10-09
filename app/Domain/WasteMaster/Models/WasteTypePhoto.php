<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property string $uuid
 * @property string $path
 */
final class WasteTypePhoto extends Model
{
    protected $fillable = ['waste_type_id', 'uuid', 'path', 'size', 'checksum', 'uploaded_by'];

    protected $hidden = ['path', 'checksum', 'uploaded_by'];

    /** @return BelongsTo<WasteType, $this> */
    public function wasteType(): BelongsTo
    {
        return $this->belongsTo(WasteType::class);
    }
}
