<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupCapacities;

use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Services\PickupService;
use App\Filament\Resources\Pickups\Models\PickupCapacities\Pages\ManagePickupCapacities;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PickupCapacityResource extends Resource
{
    protected static ?string $model = PickupCapacity::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?string $navigationLabel = 'Kapasitas Penjemputan';

    protected static ?string $modelLabel = 'kapasitas penjemputan';

    protected static ?string $pluralModelLabel = 'kapasitas penjemputan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('service_area_id')->label('Area pelayanan')->relationship('serviceArea', 'name')->required(),
            DatePicker::make('service_date')->label('Tanggal layanan')->required(),
            TextInput::make('max_addresses')->label('Batas alamat')->numeric()->integer()->minValue(0),
            TextInput::make('max_weight_kg')->label('Batas berat (kg)')->numeric()->minValue(0),
            TextInput::make('vehicle_label')->label('Kendaraan')->maxLength(120),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('service_date')
            ->columns([
                TextColumn::make('serviceArea.name')->label('Area')->searchable(),
                TextColumn::make('service_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('max_addresses')->label('Alamat')->placeholder('Tanpa batas'),
                TextColumn::make('max_weight_kg')->label('Berat')->suffix(' kg')->placeholder('Tanpa batas'),
                TextColumn::make('vehicle_label')->label('Kendaraan')->placeholder('Belum diisi'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
            ])
            ->recordActions([
                EditAction::make()->using(function (PickupCapacity $record, array $data): PickupCapacity {
                    /** @var User $actor */
                    $actor = auth()->user();
                    $area = ServiceArea::query()->findOrFail((int) $data['service_area_id']);
                    $updated = app(PickupService::class)->setCapacity($actor, $area, (string) $data['service_date'], isset($data['max_addresses']) ? (int) $data['max_addresses'] : null, isset($data['max_weight_kg']) ? (string) $data['max_weight_kg'] : null, isset($data['vehicle_label']) ? (string) $data['vehicle_label'] : null);
                    if ($updated->id !== $record->id) {
                        $record->delete();
                    }

                    return $updated;
                }),
            ]);
    }

    /** @return Builder<PickupCapacity> */
    public static function getEloquentQuery(): Builder
    {
        return PickupCapacity::query()->with('serviceArea');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePickupCapacities::route('/')];
    }
}
