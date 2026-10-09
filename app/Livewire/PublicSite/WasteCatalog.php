<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class WasteCatalog extends Component
{
    public function render(): View
    {
        $wasteTypes = app(PublicWasteCatalog::class)->types();

        return view('livewire.public-site.waste-catalog', compact('wasteTypes'));
    }
}
