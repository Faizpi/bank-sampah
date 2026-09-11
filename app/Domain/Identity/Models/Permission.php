<?php

declare(strict_types=1);

namespace App\Domain\Identity\Models;

use Database\Factories\PermissionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

final class Permission extends Model
{
    /** @use HasFactory<PermissionFactory> */
    use HasFactory;

    protected $fillable = ['name', 'description'];

    protected static function newFactory(): PermissionFactory
    {
        return PermissionFactory::new();
    }

    /** @return BelongsToMany<Role, $this> */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['granted_by', 'reason'])
            ->withTimestamps();
    }
}
