<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Models;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\WasteMaster\Models\WasteType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property MobileServiceStatus $status
 */
final class MobileService extends Model
{
    protected $fillable = ['service_number', 'rw_id', 'rt_id', 'point', 'starts_at', 'ends_at', 'status', 'capacity', 'served_count', 'notes', 'created_by'];

    protected function casts(): array
    {
        return ['starts_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime', 'status' => MobileServiceStatus::class, 'capacity' => 'integer', 'served_count' => 'integer'];
    }

    /** @return BelongsTo<Rt, $this> */
    public function rt(): BelongsTo
    {
        return $this->belongsTo(Rt::class);
    }

    /** @return BelongsTo<Rw, $this> */
    public function rw(): BelongsTo
    {
        return $this->belongsTo(Rw::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** @return BelongsToMany<User, $this> */
    public function staff(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'mobile_service_staff', 'mobile_service_id', 'staff_id');
    }

    /** @return BelongsToMany<WasteType, $this> */
    public function wasteTypes(): BelongsToMany
    {
        return $this->belongsToMany(WasteType::class, 'mobile_service_waste_types');
    }

    /** @return BelongsToMany<User, $this> */
    public function assignedStaff(): BelongsToMany
    {
        return $this->staff()->withPivot([]);
    }

    public function isOpen(): bool
    {
        return $this->status === MobileServiceStatus::Open;
    }
}
