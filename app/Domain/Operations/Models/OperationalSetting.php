<?php

declare(strict_types=1);

namespace App\Domain\Operations\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class OperationalSetting extends Model
{
    protected $table = 'settings';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'updated_by' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
