<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class Rw extends RegionModel
{
    public $timestamps = false;

    protected $table = 'rw';

    protected $fillable = ['dusun_id', 'code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return BelongsTo<Dusun, $this> */
    public function dusun(): BelongsTo
    {
        return $this->belongsTo(Dusun::class);
    }

    /** @return HasMany<Rt, $this> */
    public function rts(): HasMany
    {
        return $this->hasMany(Rt::class);
    }
}
