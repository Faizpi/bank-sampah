<?php

declare(strict_types=1);

namespace App\Domain\Identity\Enums;

enum UserStatus: string
{
    case PendingVerification = 'menunggu_verifikasi';
    case Active = 'aktif';
    case Rejected = 'ditolak';
    case Inactive = 'nonaktif';
}
