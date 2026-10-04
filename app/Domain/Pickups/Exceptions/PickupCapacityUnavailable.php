<?php

declare(strict_types=1);

namespace App\Domain\Pickups\Exceptions;

use RuntimeException;

final class PickupCapacityUnavailable extends RuntimeException
{
    /** @param list<string> $alternatives */
    public function __construct(
        string $message,
        public readonly array $alternatives = [],
    ) {
        parent::__construct($message);
    }
}
