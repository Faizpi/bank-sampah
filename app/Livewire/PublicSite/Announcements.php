<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\Communication\Services\AnnouncementService;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class Announcements extends Component
{
    public function render(AnnouncementService $announcements): View
    {
        return view('livewire.public-site.announcements', [
            'announcements' => $announcements->publicQuery()->get(),
        ]);
    }
}
