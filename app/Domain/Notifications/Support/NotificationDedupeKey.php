<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Support;

final class NotificationDedupeKey
{
    public static function for(string $eventIdentity, int $recipientId, string $template): string
    {
        return hash('sha256', implode("\0", [$eventIdentity, (string) $recipientId, $template]));
    }
}
