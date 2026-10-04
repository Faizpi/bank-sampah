<?php

declare(strict_types=1);

namespace App\Domain\Communication\Models;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\CustomersRegions\Models\Rt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\AnnouncementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @property AnnouncementAudience $audience
 * @property AnnouncementStatus $status
 * @property CarbonImmutable $publish_start
 * @property CarbonImmutable|null $publish_end
 * @property CarbonImmutable|null $published_at
 */
final class Announcement extends Model
{
    /** @use HasFactory<AnnouncementFactory> */
    use HasFactory, SoftDeletes;

    protected $fillable = ['announcement_number', 'title', 'body', 'audience', 'publish_start', 'publish_end', 'status', 'priority', 'created_by', 'published_by', 'published_at'];

    protected function casts(): array
    {
        return ['audience' => AnnouncementAudience::class, 'status' => AnnouncementStatus::class, 'publish_start' => 'immutable_datetime', 'publish_end' => 'immutable_datetime', 'published_at' => 'immutable_datetime', 'priority' => 'integer'];
    }

    protected static function newFactory(): AnnouncementFactory
    {
        return AnnouncementFactory::new();
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsTo<User, $this> */
    public function publisher(): BelongsTo
    {
        return $this->belongsTo(User::class, 'published_by');
    }

    /** @return BelongsToMany<Rt, $this> */
    public function rts(): BelongsToMany
    {
        return $this->belongsToMany(Rt::class, 'announcement_rt');
    }

    public function isVisibleAt(?string $audience = null): bool
    {
        $now = now();

        return $this->status === AnnouncementStatus::Published
            && $this->publish_start <= $now
            && ($this->publish_end === null || $this->publish_end > $now)
            && ($audience === null || $this->audience->value === $audience);
    }
}
