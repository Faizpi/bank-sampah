<?php

declare(strict_types=1);

namespace App\Domain\Programs\Models;

use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Services\TargetProgressService;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property CarbonImmutable $period_start
 * @property CarbonImmutable $period_end
 * @property TargetStatus $status
 * @property CarbonImmutable|null $closed_at
 */
final class CollectionTarget extends Model
{
    protected $fillable = ['target_number', 'name', 'purpose', 'period_start', 'period_end', 'target_weight_kg', 'status', 'is_public', 'public_min_subjects', 'created_by', 'published_by', 'closed_at', 'closed_progress_kg'];

    protected function casts(): array
    {
        return ['period_start' => 'immutable_date', 'period_end' => 'immutable_date', 'target_weight_kg' => 'decimal:3', 'status' => TargetStatus::class, 'is_public' => 'boolean', 'public_min_subjects' => 'integer', 'closed_at' => 'immutable_datetime', 'closed_progress_kg' => 'decimal:3'];
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return HasMany<TargetScope, $this> */
    public function scopes(): HasMany
    {
        return $this->hasMany(TargetScope::class);
    }

    public function progress(): string
    {
        return app(TargetProgressService::class)->progress($this);
    }
}
