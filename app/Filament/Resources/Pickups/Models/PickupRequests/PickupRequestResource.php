<?php

declare(strict_types=1);

namespace App\Filament\Resources\Pickups\Models\PickupRequests;

use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Exceptions\PickupCapacityUnavailable;
use App\Domain\Pickups\Models\PickupCapacity;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Filament\Resources\Pickups\Models\PickupRequests\Pages\ManagePickupRequests;
use App\Models\User;
use App\Support\StatusLabel;
use App\Support\WeightFormatter;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class PickupRequestResource extends Resource
{
    protected static ?string $model = PickupRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Operasional';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Penjemputan';

    protected static ?string $modelLabel = 'penjemputan';

    protected static ?string $pluralModelLabel = 'penjemputan';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('customer.name')->label('Warga')->disabled(),
            DatePicker::make('selected_date')->label('Tanggal pilihan')->disabled(),
            Select::make('service_area_id')->label('Area pelayanan')->relationship('serviceArea', 'name')->disabled(),
            TextInput::make('status')->label('Status')->formatStateUsing(fn (PickupStatus|string|null $state): string => $state === null ? '—' : StatusLabel::for($state))->disabled(),
            TextInput::make('estimated_weight_kg')->label('Perkiraan berat (kg)')->formatStateUsing(fn (?string $state): string => WeightFormatter::format($state))->disabled(),
            Textarea::make('address')->label('Alamat')->disabled(),
            Textarea::make('notes')->label('Catatan akses')->disabled()->rows(3),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('request_number')
            ->defaultSort('selected_date', 'desc')
            ->columns([
                TextColumn::make('request_number')->label('Nomor')->searchable()->sortable(),
                TextColumn::make('customer.name')->label('Nasabah')->searchable(),
                TextColumn::make('serviceArea.name')->label('Area')->searchable(),
                TextColumn::make('selected_date')->label('Tanggal')->date('d M Y')->sortable(),
                TextColumn::make('status')->label('Status')->formatStateUsing(fn (PickupStatus|string $state): string => StatusLabel::for($state))->badge(),
                TextColumn::make('assignedStaff.name')->label('Petugas')->placeholder('Belum ditugaskan'),
            ])
            ->filters([
                SelectFilter::make('status')->label('Status')->options([
                    PickupStatus::PendingReview->value => 'Menunggu pemeriksaan',
                    PickupStatus::Accepted->value => 'Diterima',
                    PickupStatus::Scheduled->value => 'Dijadwalkan',
                    PickupStatus::EnRoute->value => 'Menuju lokasi',
                    PickupStatus::PickedUp->value => 'Sudah dijemput',
                    PickupStatus::Completed->value => 'Selesai',
                    PickupStatus::Rejected->value => 'Ditolak',
                    PickupStatus::Cancelled->value => 'Dibatalkan',
                ]),
                SelectFilter::make('service_area_id')->label('Area pelayanan')->relationship('serviceArea', 'name'),
                SelectFilter::make('assigned_staff_id')->label('Petugas')->relationship('assignedStaff', 'name'),
                Filter::make('scheduled_date')->label('Tanggal layanan')->form([
                    DatePicker::make('date')->label('Tanggal'),
                ])->query(static function (Builder $query, array $data): Builder {
                    return $query->when($data['date'] ?? null, static fn (Builder $query, string $date): Builder => $query->whereDate('scheduled_date', $date));
                }),
            ])
            ->recordActions([
                Action::make('inspect')
                    ->label('Tinjau penjemputan')
                    ->icon(Heroicon::OutlinedEye)
                    ->modalHeading('Tinjau penjemputan')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('Tutup')
                    ->schema([
                        TextInput::make('customer')->label('Nasabah')->disabled(),
                        TextInput::make('area')->label('Area pelayanan')->disabled(),
                        TextInput::make('capacity')->label('Kapasitas hari tersebut')->disabled(),
                        TextInput::make('status')->label('Status')->disabled(),
                        Textarea::make('items')->label('Jenis dan perkiraan')->disabled()->rows(5),
                        Textarea::make('notes')->label('Catatan akses')->disabled()->rows(3),
                        Textarea::make('evidence')->label('Bukti foto')->disabled()->rows(3),
                        Textarea::make('timeline')->label('Riwayat status')->disabled()->rows(6),
                    ])
                    ->fillForm(fn (PickupRequest $record): array => self::inspectionData($record)),
                Action::make('accept')
                    ->label('Terima pengajuan')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (PickupRequest $record): bool => $record->status->value === 'menunggu_pemeriksaan')
                    ->authorize('review')
                    ->action(function (PickupRequest $record): ?PickupRequest {
                        try {
                            return app(PickupService::class)->review(self::actor(), $record, true);
                        } catch (PickupCapacityUnavailable $exception) {
                            self::notifyCapacityUnavailable($exception);

                            return null;
                        }
                    }),
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
                    ->action(function (PickupRequest $record, array $data): ?PickupRequest {
                        try {
                            return app(PickupService::class)->schedule(self::actor(), $record, User::query()->findOrFail((int) $data['assigned_staff_id']), (string) $data['scheduled_date']);
                        } catch (PickupCapacityUnavailable $exception) {
                            self::notifyCapacityUnavailable($exception);

                            return null;
                        }
                    }),
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

    /** @return array<string, string> */
    private static function inspectionData(PickupRequest $record): array
    {
        $record->loadMissing(['customer', 'serviceArea', 'items.wasteType', 'media', 'statusHistory']);
        $capacity = PickupCapacity::query()->where('service_area_id', $record->service_area_id)->whereDate('service_date', ($record->scheduled_date ?? $record->selected_date)->toDateString())->where('is_active', true)->first();

        return [
            'customer' => data_get($record->customer, 'name', 'Nasabah'),
            'area' => data_get($record->serviceArea, 'name', 'Area tidak tersedia'),
            'capacity' => $capacity === null ? 'Belum ada konfigurasi kapasitas.' : sprintf('%s alamat · %s kg', $capacity->max_addresses ?? 'tanpa batas alamat', WeightFormatter::format($capacity->max_weight_kg)),
            'status' => StatusLabel::for($record->status),
            'items' => $record->items->map(static fn ($item): string => sprintf('%s · %s kg · %s', data_get($item->wasteType, 'name', 'Jenis tidak tersedia'), WeightFormatter::format($item->estimated_weight_kg), $item->estimated_quantity === null ? 'jumlah —' : 'jumlah '.$item->estimated_quantity))->implode("\n"),
            'notes' => $record->notes ?? 'Tidak ada catatan akses.',
            'evidence' => $record->media->map(static fn ($media): string => $media->original_name.' · '.$media->mime_type.' · '.$media->size.' byte')->implode("\n"),
            'timeline' => $record->statusHistory->sortBy('occurred_at')->map(static fn ($history): string => CarbonImmutable::parse($history->occurred_at, 'Asia/Jakarta')->format('d M Y H:i').' · '.StatusLabel::for($history->new_status).' · '.($history->reason ?? ''))->implode("\n"),
        ];
    }

    private static function notifyCapacityUnavailable(PickupCapacityUnavailable $exception): void
    {
        $body = $exception->getMessage();
        if ($exception->alternatives !== []) {
            $body .= ' Alternatif: '.implode(', ', array_map(
                static fn (string $date): string => CarbonImmutable::createFromFormat('!Y-m-d', $date, 'Asia/Jakarta')->locale('id')->translatedFormat('d F Y'),
                $exception->alternatives,
            )).'.';
        }

        Notification::make()
            ->title('Kapasitas penjemputan tidak tersedia')
            ->body($body)
            ->danger()
            ->send();
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
