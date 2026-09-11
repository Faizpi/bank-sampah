<?php

declare(strict_types=1);

namespace App\Domain\CustomersRegions\Support;

use LogicException;

final class RegionMutationGuard
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
            throw new LogicException('Regional records may only be changed through ManageRegions.');
        }
    }
}
