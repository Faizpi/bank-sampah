<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Models\CollectionTargets\Pages;

use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetService;
use App\Filament\Resources\Programs\Models\CollectionTargets\CollectionTargetResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

final class ManageCollectionTargets extends ManageRecords
{
    protected static string $resource = CollectionTargetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalWidth(Width::SevenExtraLarge)->using(function (array $data): CollectionTarget {
                /** @var User $actor */
                $actor = auth()->user();

                try {
                    return app(TargetService::class)->create($actor, (string) $data['name'], (string) $data['purpose'], (string) $data['period_start'], (string) $data['period_end'], (string) $data['target_weight_kg'], (bool) ($data['is_public'] ?? false), $data['scopes'] ?? []);
                } catch (ValidationException $exception) {
                    Notification::make()->title('Target tidak dapat dibuat')->body(implode(' ', $exception->validator->errors()->all()))->danger()->send();

                    throw $exception;
                }
            }),
        ];
    }
}
