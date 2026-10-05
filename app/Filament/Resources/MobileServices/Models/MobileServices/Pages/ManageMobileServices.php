<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileServices\Models\MobileServices\Pages;

use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Filament\Resources\MobileServices\Models\MobileServices\MobileServiceResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Filament\Support\Enums\Width;
use Illuminate\Validation\ValidationException;

final class ManageMobileServices extends ManageRecords
{
    protected static string $resource = MobileServiceResource::class;

    protected function onValidationError(ValidationException $exception): void
    {
        $message = collect($exception->errors())->flatten()->first();
        Notification::make()
            ->title('Layanan belum dapat disimpan')
            ->body(is_string($message) ? $message : 'Periksa wilayah, jadwal, petugas, dan jenis sampah yang dipilih.')
            ->danger()
            ->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->modalWidth(Width::SevenExtraLarge)->using(function (array $data): MobileService {
                /** @var User $actor */
                $actor = auth()->user();

                return app(MobileServiceService::class)->create($actor, isset($data['rw_id']) ? (int) $data['rw_id'] : null, isset($data['rt_id']) ? (int) $data['rt_id'] : null, (string) $data['point'], (string) $data['starts_at'], (string) $data['ends_at'], (int) $data['capacity'], (string) ($data['notes'] ?? ''), array_map('intval', $data['staff_ids'] ?? []), array_map('intval', $data['waste_type_ids'] ?? []));
            }),
        ];
    }
}
