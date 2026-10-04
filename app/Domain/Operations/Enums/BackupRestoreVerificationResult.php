<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum BackupRestoreVerificationResult: string
{
    case Passed = 'lulus';
    case Failed = 'gagal';
}
