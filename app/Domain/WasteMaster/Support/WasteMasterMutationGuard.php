<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Support;

use LogicException;

final class WasteMasterMutationGuard
{
    private static int $depth = 0;

    public static function run(callable $callback): mixed
    {
        self::$depth++;

        try {
            return $callback();
        } finally {
            self::$depth--;
        }
    }

    public static function ensureAllowed(): void
    {
        if (self::$depth === 0) {
            throw new LogicException('Waste master records may only be changed through ManageWasteMaster.');
        }
    }
}
