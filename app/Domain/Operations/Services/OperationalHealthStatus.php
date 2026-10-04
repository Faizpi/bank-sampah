<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

enum OperationalHealthStatus: string
{
    case Ok = 'ok';
    case Configured = 'configured';
    case Degraded = 'degraded';
    case NotApplicable = 'not_applicable';
}
