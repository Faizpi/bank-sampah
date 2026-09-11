<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use RuntimeException;

abstract class DomainException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly string $errorCode,
    ) {
        parent::__construct($message);
    }
}
