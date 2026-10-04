<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Events;

use App\Domain\Notifications\Data\NotificationPayload;
use Illuminate\Contracts\Events\ShouldDispatchAfterCommit;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

final class NotificationRequested implements ShouldDispatchAfterCommit
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly NotificationPayload $payload) {}
}
