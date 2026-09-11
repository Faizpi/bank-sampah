<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\CitizenVerifications\Pages;

use App\Filament\Resources\Identity\Models\CitizenVerifications\CitizenVerificationResource;
use Filament\Resources\Pages\ManageRecords;

final class ManageCitizenVerifications extends ManageRecords
{
    protected static string $resource = CitizenVerificationResource::class;
}
