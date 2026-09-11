<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Contracts;

enum RegionScope: string
{
    case Own = 'own';
    case Assigned = 'assigned';
    case Area = 'area';
    case All = 'all';
}
