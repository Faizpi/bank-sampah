<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Models;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Database\Factories\PickupRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

/**
 * @property int $id
 * @property int $customer_id
 * @property int $rt_id
 * @property int $service_area_id
 * @property int|null $assigned_staff_id
 * @property int|null $deposit_id
 * @property PickupStatus $status
 * @property CarbonImmutable $selected_date
 * @property CarbonImmutable|null $scheduled_date
 * @property string|null $estimated_weight_kg
 * @property CarbonImmutable|null $accepted_at
 * @property CarbonImmutable|null $scheduled_at
 * @property CarbonImmutable|null $en_route_at
 * @property CarbonImmutable|null $picked_up_at
 * @property CarbonImmutable|null $completed_at
 */
final class PickupRequest extends Model
{
    /** @use HasFactory<PickupRequestFactory> */
    use HasFactory;

    protected static function newFactory(): PickupRequestFactory
    {
        return PickupRequestFactory::new();
    }

    protected $fillable = [
        'request_number', 'customer_id', 'rt_id', 'service_area_id', 'address', 'selected_date',
        'scheduled_date', 'estimated_weight_kg', 'notes', 'status', 'rejection_reason',
        'cancellation_reason', 'assigned_staff_id', 'deposit_id', 'accepted_at', 'scheduled_at',
        'en_route_at', 'picked_up_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PickupStatus::class,
            'selected_date' => 'immutable_date',
            'scheduled_date' => 'immutable_date',
            'estimated_weight_kg' => 'decimal:3',
            'accepted_at' => 'immutable_datetime',
            'scheduled_at' => 'immutable_datetime',
            'en_route_at' => 'immutable_datetime',
            'picked_up_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function customer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'customer_id');
    }

    /** @return BelongsTo<CustomerProfile, $this> */
    public function customerProfile(): BelongsTo
    {
        return $this->belongsTo(CustomerProfile::class, 'customer_id', 'user_id');
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /** @return BelongsTo<ServiceArea, $this> */
    public function serviceArea(): BelongsTo
    {
        return $this->belongsTo(ServiceArea::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedStaff(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_staff_id');
    }

    /** @return BelongsTo<Deposit, $this> */
    public function deposit(): BelongsTo
    {
        return $this->belongsTo(Deposit::class);
    }

    /** @return HasMany<PickupItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(PickupItem::class);
    }

    /** @return MorphMany<Media, $this> */
    public function media(): MorphMany
    {
        return $this->morphMany(Media::class, 'attachable');
    }

    /** @return MorphMany<StatusHistory, $this> */
    public function statusHistory(): MorphMany
    {
        return $this->morphMany(StatusHistory::class, 'subject');
    }

    public function canTransitionTo(PickupStatus $next): bool
    {
        return $this->status->canTransitionTo($next);
    }

    public function isTerminal(): bool
    {
        return in_array($this->status, [PickupStatus::Completed, PickupStatus::Rejected, PickupStatus::Cancelled], true);
    }
}
