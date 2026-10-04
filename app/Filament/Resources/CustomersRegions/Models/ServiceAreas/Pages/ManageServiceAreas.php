<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\ServiceAreas\Pages;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\ServiceAreaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageServiceAreas extends ManageRecords
{
    protected static string $resource = ServiceAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()->using(fn (array $data): ServiceArea => app(ManageRegions::class)->createServiceArea(auth()->user(), $data['name'], Rt::query()->findMany($data['rts'] ?? [])->all()))];
    }
}
