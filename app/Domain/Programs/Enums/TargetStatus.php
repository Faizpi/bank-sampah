<?php

declare(strict_types=1);

namespace App\Domain\Programs\Enums;

enum TargetStatus: string
{
    case Draft = 'draf';
    case Active = 'aktif';
    case Closed = 'ditutup';
    case Cancelled = 'dibatalkan';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Active, self::Cancelled], true),
            self::Active => in_array($next, [self::Closed, self::Cancelled], true),
            self::Closed, self::Cancelled => false,
        };
    }
}
