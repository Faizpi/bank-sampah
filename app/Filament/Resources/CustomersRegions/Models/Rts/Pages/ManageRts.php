<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\Rts\Pages;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Filament\Resources\CustomersRegions\Models\Rts\RtResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageRts extends ManageRecords
{
    protected static string $resource = RtResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): Rt => app(ManageRegions::class)->createRt(auth()->user(), Rw::query()->findOrFail($data['rw_id']), $data['code'], $data['name']))];
    }
}
