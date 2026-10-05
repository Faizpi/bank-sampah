<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WastePrices\Pages;

use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\WasteMaster\Models\WastePrices\WastePriceResource;
use Carbon\CarbonImmutable;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class ManageWastePrices extends ManageRecords
{
    protected static string $resource = WastePriceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->using(function (array $data): WastePrice {
                /** @var WasteType $type */
                $type = WasteType::query()->findOrFail($data['waste_type_id']);
                /** @var WasteCondition $condition */
                $condition = WasteCondition::query()->findOrFail($data['waste_condition_id']);

                try {
                    return app(ManageWastePricing::class)->createPeriod(
                        auth()->user(),
                        $type,
                        $condition,
                        (int) $data['price'],
                        CarbonImmutable::parse((string) $data['effective_from']),
                        is_string($data['effective_to'] ?? null) ? CarbonImmutable::parse($data['effective_to']) : null,
                        self::correlationId(),
                        (bool) ($data['zero_price_confirmed'] ?? false),
                    );
                } catch (ValidationException $exception) {
                    $message = collect($exception->errors())->flatten()->first();
                    Notification::make()
                        ->title('Harga sampah belum dapat disimpan')
                        ->body(is_string($message) ? $message : 'Periksa jenis, kondisi, harga, dan periode berlaku yang dipilih.')
                        ->danger()
                        ->send();

                    throw $exception;
                }
            }),
        ];
    }

    private static function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) && Str::isUuid($correlationId) ? strtolower($correlationId) : (string) Str::uuid();
    }
}
