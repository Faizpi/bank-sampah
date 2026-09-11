<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Models;

use App\Domain\Platform\Models\Media;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * @property int $id
 * @property string $code
 * @property string $name
 * @property string $contents
 * @property int $value
 * @property string $status
 * @property CarbonImmutable|null $active_from
 * @property CarbonImmutable|null $active_until
 */
final class GroceryPackage extends Model
{
    protected $fillable = ['code', 'name', 'contents', 'value', 'active_from', 'active_until', 'status', 'media_id'];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'active_from' => 'immutable_date',
            'active_until' => 'immutable_date',
        ];
    }

    /** @return BelongsTo<Media, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function isAvailableOn(CarbonImmutable $date): bool
    {
        return $this->status === 'aktif'
            && ($this->active_from === null || $this->active_from->lessThanOrEqualTo($date))
            && ($this->active_until === null || $this->active_until->greaterThanOrEqualTo($date));
    }

    protected static function booted(): void
    {
        self::deleting(static function (): void {
            throw new LogicException('Paket sembako adalah master histori dan tidak dapat dihapus.');
        });
    }
}
