<?php

declare(strict_types=1);

namespace App\Domain\Communication\Enums;

enum AnnouncementStatus: string
{
    case Draft = 'draf';
    case Published = 'terbit';
    case Inactive = 'nonaktif';
}
