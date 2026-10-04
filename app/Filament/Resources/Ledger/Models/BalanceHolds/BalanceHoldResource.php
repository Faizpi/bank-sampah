<?php

declare(strict_types=1);

namespace App\Filament\Resources\Ledger\Models\BalanceHolds;

use App\Authorization\PermissionChecker;
use App\Domain\Ledger\Models\BalanceHold;
use App\Domain\Ledger\Services\LedgerService;
use App\Filament\Resources\Ledger\Models\BalanceHolds\Pages\ManageBalanceHolds;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class BalanceHoldResource extends Resource
{
    protected static ?string $model = BalanceHold::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedLockClosed;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Dana ditahan';

    protected static ?string $modelLabel = 'dana ditahan';

    protected static ?string $pluralModelLabel = 'dana ditahan';

    public static function shouldRegisterNavigation(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'ledger.adjust');
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'ledger.view');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Textarea::make('hold_number')->label('Nomor dana ditahan')->disabled(),
            Textarea::make('source_key')->label('Referensi sumber')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('hold_number')
            ->defaultSort('held_at', 'desc')
            ->columns([
                TextColumn::make('hold_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('account.user.name')->label('Nasabah')->searchable(),
                TextColumn::make('amount')->label('Nominal')->money('IDR')->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('held_at')->label('Dibuat')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('released_at')->label('Dilepas')->dateTime('d M Y H:i')->placeholder('—'),
                TextColumn::make('converted_at')->label('Dikonversi')->dateTime('d M Y H:i')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    BalanceHold::STATUS_ACTIVE => 'Aktif',
                    BalanceHold::STATUS_CONVERTED => 'Dikonversi',
                    BalanceHold::STATUS_RELEASED => 'Dilepas',
                ]),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Lihat dana ditahan')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Detail dana ditahan dan sumber')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        Textarea::make('source')->label('Sumber penahanan')->disabled()->rows(4),
                        Textarea::make('account')->label('Rekening dan status')->disabled()->rows(3),
                    ])
                    ->fillForm(fn (BalanceHold $record): array => [
                        'source' => json_encode(['source_type' => $record->source_type, 'source_id' => $record->source_id, 'source_key' => $record->source_key], JSON_PRETTY_PRINT),
                        'account' => json_encode(['ledger_account_id' => $record->ledger_account_id, 'amount' => $record->amount, 'status' => $record->status], JSON_PRETTY_PRINT),
                    ]),
            ]);
    }

    /** @return Builder<BalanceHold> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return $actor instanceof User
            ? app(LedgerService::class)->visibleHolds($actor)
            : BalanceHold::query()->whereKey([]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageBalanceHolds::route('/')];
    }
}
