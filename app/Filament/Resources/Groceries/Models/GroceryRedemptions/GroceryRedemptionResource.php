<?php

declare(strict_types=1);

namespace App\Filament\Resources\Groceries\Models\GroceryRedemptions;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Filament\Resources\Groceries\Models\GroceryRedemptions\Pages\ManageGroceryRedemptions;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class GroceryRedemptionResource extends Resource
{
    protected static ?string $model = GroceryRedemption::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static string|UnitEnum|null $navigationGroup = 'Transaksi & Saldo';

    protected static ?int $navigationSort = 50;

    protected static ?string $navigationLabel = 'Penukaran Sembako';

    protected static ?string $modelLabel = 'penukaran sembako';

    protected static ?string $pluralModelLabel = 'penukaran sembako';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('request_number')->label('Nomor')->disabled(),
            Textarea::make('package_snapshot')->label('Snapshot paket')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('request_number')->columns([
            TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
            TextColumn::make('customer.name')->label('Warga')->searchable(),
            TextColumn::make('package.name')->label('Paket')->searchable(),
            TextColumn::make('value_snapshot')->label('Nilai')->money('IDR'),
            TextColumn::make('source_type')->label('Sumber')->badge(),
            TextColumn::make('status')->label('Status')->badge(),
            TextColumn::make('approver.name')->label('Approver')->placeholder('Belum diputuskan'),
        ])->recordActions([
            Action::make('approve')
                ->label('Setujui')
                ->icon(Heroicon::OutlinedCheckCircle)
                ->color('success')
                ->visible(fn (GroceryRedemption $record): bool => $record->status->value === 'menunggu_verifikasi')
                ->authorize('approve')
                ->schema([Textarea::make('availability_note')->label('Catatan ketersediaan manual')->required()->minLength(3)->maxLength(1000)->rows(4)])
                ->action(fn (GroceryRedemption $record, array $data): GroceryRedemption => app(GroceryService::class)->approve(auth()->user(), $record, true, (string) $data['availability_note'])),
            Action::make('reject')
                ->label('Tolak')
                ->icon(Heroicon::OutlinedXCircle)
                ->color('danger')
                ->visible(fn (GroceryRedemption $record): bool => $record->status->value === 'menunggu_verifikasi')
                ->authorize('approve')
                ->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(4)])
                ->action(fn (GroceryRedemption $record, array $data): GroceryRedemption => app(GroceryService::class)->approve(auth()->user(), $record, false, null, (string) $data['reason'])),
            Action::make('prepare')
                ->label('Mulai siapkan')
                ->icon(Heroicon::OutlinedCube)
                ->visible(fn (GroceryRedemption $record): bool => $record->status->value === 'disetujui')
                ->authorize('prepare')
                ->action(fn (GroceryRedemption $record): GroceryRedemption => app(GroceryService::class)->prepare(auth()->user(), $record)),
            Action::make('ready')
                ->label('Tandai siap')
                ->icon(Heroicon::OutlinedCheck)
                ->visible(fn (GroceryRedemption $record): bool => $record->status->value === 'sedang_disiapkan')
                ->authorize('prepare')
                ->action(fn (GroceryRedemption $record): GroceryRedemption => app(GroceryService::class)->ready(auth()->user(), $record)),
        ]);
    }

    /** @return Builder<GroceryRedemption> */
    public static function getEloquentQuery(): Builder
    {
        return app(GroceryService::class)->visibleFor(auth()->user());
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageGroceryRedemptions::route('/')];
    }
}
