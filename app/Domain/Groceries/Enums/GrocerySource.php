<?php

declare(strict_types=1);

namespace App\Domain\Groceries\Enums;

enum GrocerySource: string
{
    case Balance = 'saldo';
    case FreeAid = 'bantuan_gratis';

    public function usesBalance(): bool
    {
        return $this === self::Balance;
    }
}
