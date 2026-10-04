<?php

declare(strict_types=1);

namespace App\Domain\Communication\Enums;

enum AnnouncementAudience: string
{
    case Public = 'publik';
    case Internal = 'internal';
    case Citizen = 'warga';
    case Officer = 'petugas';
    case Region = 'wilayah';
}
