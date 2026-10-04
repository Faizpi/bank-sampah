<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\MobileServices\Services\MobileServiceService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class MobileSchedule extends Component
{
    public function render(MobileServiceService $services): View
    {
        return view('livewire.public-site.mobile-schedule', ['services' => $services->publicQuery()->with('rt')->get()]);
    }
}
