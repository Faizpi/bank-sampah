<?php

declare(strict_types=1);

namespace App\Livewire\Treasurer;

use App\Livewire\Concerns\ServiceTaskQueue;
use Livewire\Attributes\Layout;

#[Layout('layouts.officer')]
final class WithdrawalPayments extends ServiceTaskQueue
{
    protected function isCash(): bool
    {
        return true;
    }
}
