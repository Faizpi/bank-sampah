<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Statistics\Services\LandingStatistics;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use Illuminate\Contracts\View\View;

final class LandingController
{
    public function __invoke(LandingStatistics $statistics, PublicWasteCatalog $catalog): View
    {
        return view('welcome', ['summary' => $statistics->summary(), 'wasteTypes' => $catalog->types(8)]);
    }
}
