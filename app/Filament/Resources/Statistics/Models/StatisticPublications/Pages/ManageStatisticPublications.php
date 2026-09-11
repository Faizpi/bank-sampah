<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statistics\Models\StatisticPublications\Pages;

use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\Statistics\Services\StatisticsService;
use App\Filament\Resources\Statistics\Models\StatisticPublications\StatisticPublicationResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageStatisticPublications extends ManageRecords
{
    protected static string $resource = StatisticPublicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): StatisticPublication {
                /** @var User $actor */
                $actor = auth()->user();

                return app(StatisticsService::class)->configurePublic($actor, array_values(array_map('strval', $data['metrics'] ?? [])), array_values(array_map('strval', $data['dimensions'] ?? [])), (int) $data['privacy_threshold'], (bool) ($data['is_active'] ?? false));
            }),
        ];
    }
}
