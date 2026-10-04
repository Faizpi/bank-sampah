<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Dusuns\Pages;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Filament\Resources\CustomersRegions\Models\Dusuns\DusunResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageDusuns extends ManageRecords
{
    protected static string $resource = DusunResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(fn (array $data): Dusun => app(ManageRegions::class)->createDusun(auth()->user(), $data['code'], $data['name'])),
        ];
    }
}
