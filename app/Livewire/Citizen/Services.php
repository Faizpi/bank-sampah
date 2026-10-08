<?php

declare(strict_types=1);

namespace App\Livewire\Citizen;

use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.citizen')]
final class Services extends Component
{
    public function render(): View
    {
        return view('livewire.citizen.services');
    }
}
