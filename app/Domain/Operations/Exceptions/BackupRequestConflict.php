<?php

declare(strict_types=1);

namespace App\Domain\Operations\Exceptions;

use App\Domain\Shared\DomainException;

final class BackupRequestConflict extends DomainException
{
    public function __construct()
    {
        parent::__construct(
            'Backup operator key sudah digunakan untuk payload berbeda.',
            'backup_request_operator_key_conflict',
        );
    }
}
