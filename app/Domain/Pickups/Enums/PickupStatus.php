<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Enums;

enum PickupStatus: string
{
    case PendingReview = 'menunggu_pemeriksaan';
    case Accepted = 'diterima';
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
            self::PendingReview => [self::Accepted->value, self::Rejected->value, self::Cancelled->value],
            self::Accepted => [self::Scheduled->value, self::Cancelled->value],
            self::Scheduled => [self::EnRoute->value, self::Cancelled->value],
            self::EnRoute => [self::PickedUp->value],
            self::PickedUp => [self::Completed->value],
            self::Completed, self::Rejected, self::Cancelled => [],
        };
    }

    public function canTransitionTo(self $next): bool
    {
        return in_array($next->value, $this->allowedNextStatuses(), true);
    }
}
