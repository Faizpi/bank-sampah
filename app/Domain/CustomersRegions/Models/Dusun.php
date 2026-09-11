<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

final class Dusun extends RegionModel
{
    public $timestamps = false;

    protected $table = 'dusun';

    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    /** @return HasMany<Rw, $this> */
    public function rws(): HasMany
    {
        return $this->hasMany(Rw::class);
    }
}
