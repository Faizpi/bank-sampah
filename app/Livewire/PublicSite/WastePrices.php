<?php

declare(strict_types=1);

namespace App\Livewire\PublicSite;

use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('layouts.public')]
final class WastePrices extends Component
{
    public function render(): View
    {
        $prices = app(PublicWasteCatalog::class)->prices();

        return view('livewire.public-site.waste-prices', compact('prices'));
    }
}
