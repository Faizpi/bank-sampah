<?php

declare(strict_types=1);

namespace App\Domain\Reports\Enums;

enum ReportExportStatus: string
{
    case Pending = 'menunggu';
    case Processing = 'diproses';
    case Succeeded = 'berhasil';
    case Failed = 'gagal';
    case Expired = 'kedaluwarsa';
}
