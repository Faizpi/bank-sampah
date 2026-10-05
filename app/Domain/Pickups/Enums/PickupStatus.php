<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Enums;

enum PickupStatus: string
{
    case Waiting = 'menunggu_petugas';
    case Scheduled = 'dijadwalkan';
    case EnRoute = 'menuju_lokasi';
    case PickedUp = 'dijemput';
    case Completed = 'selesai';
    case Rejected = 'ditolak';
    case Cancelled = 'dibatalkan';

    /** @return list<string> */
    public function allowedNextStatuses(): array
    {
        return match ($this) {
            self::Waiting => [self::Scheduled->value, self::Rejected->value, self::Cancelled->value],
            self::Scheduled => [self::Waiting->value, self::EnRoute->value, self::Cancelled->value],
            self::EnRoute => [self::Waiting->value, self::PickedUp->value, self::Cancelled->value],
            self::PickedUp => [self::Completed->value, self::Cancelled->value],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, $this->allowedNextStatuses(), true);
    }
}
