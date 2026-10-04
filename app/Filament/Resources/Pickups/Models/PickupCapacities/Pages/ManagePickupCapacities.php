<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupCapacities\Pages;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Services\PickupService;
use App\Filament\Resources\Pickups\Models\PickupCapacities\PickupCapacityResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManagePickupCapacities extends ManageRecords
{
    protected static string $resource = PickupCapacityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): PickupCapacity {
                /** @var User $actor */
                $actor = auth()->user();
                $area = ServiceArea::query()->findOrFail((int) $data['service_area_id']);

                return app(PickupService::class)->setCapacity($actor, $area, (string) $data['service_date'], isset($data['max_addresses']) ? (int) $data['max_addresses'] : null, isset($data['max_weight_kg']) ? (string) $data['max_weight_kg'] : null, isset($data['vehicle_label']) ? (string) $data['vehicle_label'] : null);
            }),
        ];
    }
}
