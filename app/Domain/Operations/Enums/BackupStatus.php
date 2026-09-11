<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

enum BackupStatus: string
{
    case Pending = 'menunggu';
    case Processing = 'diproses';
    case Succeeded = 'berhasil';
    case Failed = 'gagal';

    public function canTransitionTo(self $target): bool
    {
        return match ($this) {
            self::Pending => $target === self::Processing,
            self::Processing => in_array($target, [self::Succeeded, self::Failed], true),
            self::Succeeded, self::Failed => false,
        };
    }
}
