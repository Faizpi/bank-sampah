<?php

declare(strict_types=1);

namespace App\Domain\MobileServices\Enums;

enum MobileServiceStatus: string
{
    case Draft = 'draf';
    case Published = 'dipublikasikan';
    case Open = 'dibuka';
    case Closed = 'ditutup';
    case Cancelled = 'dibatalkan';

    public function canTransitionTo(self $next): bool
    {
        return match ($this) {
            self::Draft => in_array($next, [self::Published, self::Cancelled], true),
            self::Published => in_array($next, [self::Open, self::Cancelled], true),
            self::Open => in_array($next, [self::Closed], true),
            self::Closed, self::Cancelled => false,
        };
    }
}
