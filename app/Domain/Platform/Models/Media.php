<?php

declare(strict_types=1);

namespace App\Domain\Platform\Models;

use App\Domain\Platform\Enums\MediaVisibility;
use App\Models\User;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'uuid',
    'disk',
    'path',
    'original_name',
    'mime_type',
    'size',
    'checksum',
    'visibility',
    'uploader_id',
    'attachable_type',
    'attachable_id',
])]
#[Hidden(['path', 'checksum'])]
/**
 * @property MediaVisibility $visibility
 * @property string $disk
 * @property string $path
 * @property string $mime_type
 * @property string $original_name
 * @property int|null $attachable_id
 */
final class Media extends Model
{
    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'visibility' => MediaVisibility::class,
            'uploader_id' => 'integer',
            'attachable_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploader_id');
    }

    /** @return MorphTo<Model, $this> */
    public function attachable(): MorphTo
    {
        return $this->morphTo();
    }
}
