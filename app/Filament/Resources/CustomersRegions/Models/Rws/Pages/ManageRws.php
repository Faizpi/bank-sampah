<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Rws\Pages;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rw;
use App\Filament\Resources\CustomersRegions\Models\Rws\RwResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageRws extends ManageRecords
{
    protected static string $resource = RwResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): Rw => app(ManageRegions::class)->createRw(auth()->user(), Dusun::query()->findOrFail($data['dusun_id']), $data['code'], $data['name']))];
    }
}
