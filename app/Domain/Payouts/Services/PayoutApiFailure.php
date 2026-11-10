<?php

declare(strict_types=1);

namespace App\Domain\Payouts\Services;

use RuntimeException;

final class PayoutApiFailure extends RuntimeException
{
    public function __construct(public readonly string $codeName, public readonly bool $uncertain = true)
    {
        parent::__construct($codeName);
    }
}
