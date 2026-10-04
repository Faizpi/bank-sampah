<?php

declare(strict_types=1);

namespace App\Filament\Resources\CustomersRegions\Models\ServiceAreas;

use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Filament\Resources\CustomersRegions\Models\ServiceAreas\Pages\ManageServiceAreas;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class ServiceAreaResource extends Resource
{
    protected static ?string $model = ServiceArea::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMapPin;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Area Pelayanan';

    protected static ?string $recordTitleAttribute = 'name';

    public static function shouldRegisterNavigation(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(100)->unique(ignoreRecord: true),
            Select::make('rts')->relationship('rts', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true)->whereHas('rw', fn ($rw) => $rw->where('is_active', true)->whereHas('dusun', fn ($dusun) => $dusun->where('is_active', true))))->multiple()->preload(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            TextColumn::make('name')->searchable(),
            TextColumn::make('rts_count')->counts('rts')->label('RT'),
            IconColumn::make('is_active')->boolean(),
        ])->recordActions([
            EditAction::make()->using(fn (ServiceArea $record, array $data): ServiceArea => tap($record, fn () => app(ManageRegions::class)->updateServiceArea(auth()->user(), $record, $data['name'], Rt::query()->findMany($data['rts'] ?? [])->all()))),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (ServiceArea $record): string => "Aktifkan area {$record->name}?")->modalDescription('Area ini kembali tersedia untuk penjadwalan layanan. RT yang terhubung harus aktif.')->modalSubmitActionLabel('Aktifkan area')->visible(fn (ServiceArea $record): bool => ! $record->is_active)->action(fn (ServiceArea $record) => app(ManageRegions::class)->activate(auth()->user(), $record)),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (ServiceArea $record): string => "Nonaktifkan area {$record->name}?")->modalDescription('Area ini tidak tersedia untuk penjadwalan baru. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan area')->visible(fn (ServiceArea $record): bool => $record->is_active)->action(fn (ServiceArea $record) => app(ManageRegions::class)->deactivate(auth()->user(), $record)),
        ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageServiceAreas::route('/')];
    }
}
