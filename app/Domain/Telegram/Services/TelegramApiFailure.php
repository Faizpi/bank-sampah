<?php

declare(strict_types=1);

namespace App\Domain\Telegram\Services;

use RuntimeException;

final class TelegramApiFailure extends RuntimeException
{
    public function __construct(public readonly string $errorCode, public readonly int $retryAfter = 0, public readonly bool $uncertain = false, public readonly bool $blocked = false)
    {
        // Never retain the HTTP exception: its URL contains the bot credential.
        parent::__construct($errorCode);
    }
}
