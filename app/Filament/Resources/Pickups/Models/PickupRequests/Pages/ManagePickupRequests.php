<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupRequests\Pages;

use App\Filament\Resources\Pickups\Models\PickupRequests\PickupRequestResource;
use Filament\Resources\Pages\ManageRecords;

final class ManagePickupRequests extends ManageRecords
{
    protected static string $resource = PickupRequestResource::class;
}
