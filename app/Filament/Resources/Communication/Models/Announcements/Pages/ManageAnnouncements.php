<?php

declare(strict_types=1);

namespace App\Filament\Resources\Communication\Models\Announcements\Pages;

use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Services\AnnouncementService;
use App\Filament\Resources\Communication\Models\Announcements\AnnouncementResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageAnnouncements extends ManageRecords
{
    protected static string $resource = AnnouncementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): Announcement {
                /** @var User $actor */
                $actor = auth()->user();

                return app(AnnouncementService::class)->create($actor, (string) $data['title'], (string) $data['body'], (string) $data['audience'], (string) $data['publish_start'], $data['publish_end'] ?? null, array_map('intval', $data['rt_ids'] ?? []), (int) $data['priority']);
            }),
        ];
    }
}
