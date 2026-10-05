<?php

declare(strict_types=1);

namespace App\Livewire\Officer;

use App\Livewire\Concerns\ServiceTaskQueue;
use Livewire\Attributes\Layout;

#[Layout('layouts.officer')]
final class GroceryTasks extends ServiceTaskQueue
{
    protected function isCash(): bool
    {
        return false;
    }
}
