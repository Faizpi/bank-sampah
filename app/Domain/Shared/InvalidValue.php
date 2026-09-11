<?php

declare(strict_types=1);

namespace App\Domain\Shared;

final class InvalidValue extends DomainException
{
    public function __construct(string $message)
    {
        parent::__construct($message, 'invalid_value');
    }
}
