<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupRequests;

use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Filament\Resources\Pickups\Models\PickupRequests\Pages\ManagePickupRequests;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PickupRequestResource extends Resource
{
    protected static ?string $model = PickupRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional Lapangan';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Penjemputan';

    protected static ?string $modelLabel = 'penjemputan';

    protected static ?string $pluralModelLabel = 'penjemputan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            DatePicker::make('selected_date')->label('Tanggal pilihan')->disabled(),
            Select::make('service_area_id')->label('Area pelayanan')->relationship('serviceArea', 'name')->disabled(),
            Textarea::make('address')->label('Alamat')->disabled(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Warga')->searchable(),
                TextColumn::make('serviceArea.name')->label('Area')->searchable(),
                TextColumn::make('selected_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('status')->label('Status')->formatStateUsing(fn (string $state): string => str_replace('_', ' ', $state))->badge(),
                TextColumn::make('assignedStaff.name')->label('Petugas')->placeholder('Belum ditugaskan'),
            ])
            ->recordActions([
                Action::make('accept')
                    ->label('Terima')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PickupRequest $record): bool => $record->status->value === 'menunggu_pemeriksaan')
                    ->authorize('review')
                    ->action(fn (PickupRequest $record): PickupRequest => app(PickupService::class)->review(self::actor(), $record, true)),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (PickupRequest $record): bool => $record->status->value === 'menunggu_pemeriksaan')
                    ->authorize('review')
                    ->schema([Textarea::make('reason')->label('Alasan penolakan')->required()->minLength(10)->maxLength(1000)->rows(4)])
                    ->action(fn (PickupRequest $record, array $data): PickupRequest => app(PickupService::class)->review(self::actor(), $record, false, (string) $data['reason'])),
                Action::make('schedule')
                    ->label('Jadwalkan')
                    ->icon(Heroicon::OutlinedCalendarDays)
                    ->visible(fn (PickupRequest $record): bool => $record->status->value === 'diterima')
                    ->authorize('schedule')
                    ->schema([
                        Select::make('assigned_staff_id')->label('Petugas')->options(fn (PickupRequest $record): array => User::query()->whereHas('staffProfile', fn (Builder $profile): Builder => $profile->where('service_area_id', $record->service_area_id))->where('status', 'aktif')->pluck('name', 'id')->all())->required(),
                        DatePicker::make('scheduled_date')->label('Tanggal jadwal')->required(),
                    ])
                    ->action(fn (PickupRequest $record, array $data): PickupRequest => app(PickupService::class)->schedule(self::actor(), $record, User::query()->findOrFail((int) $data['assigned_staff_id']), (string) $data['scheduled_date'])),
            ]);
    }

    /** @return Builder<PickupRequest> */
    public static function getEloquentQuery(): Builder
    {
        return PickupRequest::query()->with(['customer', 'serviceArea', 'assignedStaff']);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePickupRequests::route('/')];
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
