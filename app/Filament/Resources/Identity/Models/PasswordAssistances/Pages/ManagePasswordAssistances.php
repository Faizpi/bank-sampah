<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\PasswordAssistances\Pages;

use App\Filament\Resources\Identity\Models\PasswordAssistances\PasswordAssistanceResource;
use Filament\Resources\Pages\ManageRecords;

final class ManagePasswordAssistances extends ManageRecords
{
    protected static string $resource = PasswordAssistanceResource::class;
}
