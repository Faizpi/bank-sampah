<?php

declare(strict_types=1);

namespace App\Filament\Resources\MobileServices\Models\MobileServices;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\MobileServices\Services\MobileServiceService;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\MobileServices\Models\MobileServices\Pages\ManageMobileServices;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class MobileServiceResource extends Resource
{
    protected static ?string $model = MobileService::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static string|UnitEnum|null $navigationGroup = 'Program';

    protected static ?int $navigationSort = 30;

    protected static ?string $navigationLabel = 'Layanan Keliling';

    protected static ?string $modelLabel = 'layanan keliling';

    protected static ?string $pluralModelLabel = 'layanan keliling';

    protected static ?string $recordTitleAttribute = 'service_number';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Jadwal')->schema([
                Select::make('rw_id')->label('RW')->helperText('Opsional. Pilih RW untuk membatasi pilihan RT, atau gunakan RW saja sebagai cakupan layanan.')->options(fn (): array => Rw::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable()->live()->afterStateUpdated(fn (Set $set): mixed => $set('rt_id', null)),
                Select::make('rt_id')->label('RT')->helperText('Opsional. Tanpa RW, semua RT aktif tersedia; dengan RW, hanya RT dalam RW tersebut yang ditampilkan.')->options(function (Get $get): array {
                    $rwId = $get('rw_id');

                    return Rt::query()
                        ->with('rw:id,name')
                        ->where('is_active', true)
                        ->when(filled($rwId), fn (Builder $query): Builder => $query->where('rw_id', (int) $rwId))
                        ->orderBy('name')
                        ->get()
                        ->mapWithKeys(fn (Rt $rt): array => [$rt->id => filled($rwId) ? $rt->name : "{$rt->name} — {$rt->rw->name}"])
                        ->all();
                })->searchable(),
                TextInput::make('point')->label('Titik layanan')->required()->minLength(3)->maxLength(255),
                DateTimePicker::make('starts_at')->label('Mulai')->seconds(false)->native(false)->required(),
                DateTimePicker::make('ends_at')->label('Selesai')->seconds(false)->native(false)->after('starts_at')->required(),
                TextInput::make('capacity')->label('Kapasitas warga')->numeric()->integer()->minValue(1)->maxValue(1000000)->required(),
                Select::make('staff_ids')->label('Petugas layanan')->helperText('Pilih petugas aktif yang dapat mengoperasikan layanan keliling.')->multiple()->required()->minItems(1)->searchable()->preload()->options(fn (): array => User::query()->where('status', UserStatus::Active)->whereHas('staffProfile', fn (Builder $query): Builder => $query)->whereHas('roles.permissions', fn (Builder $query): Builder => $query->where('permissions.name', 'mobile-service.operate'))->orderBy('name')->pluck('name', 'id')->all()),
                Select::make('waste_type_ids')->label('Jenis sampah yang diterima')->helperText('Pilih jenis sampah aktif yang dapat disetor pada layanan ini.')->multiple()->required()->minItems(1)->searchable()->preload()->options(fn (): array => WasteType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all()),
                Textarea::make('notes')->label('Catatan')->maxLength(2000)->rows(4)->columnSpanFull(),
            ])->columns(['default' => 1, 'md' => 2]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('service_number')->defaultSort('starts_at', 'desc')->columns([
            TextColumn::make('service_number')->label('Nomor')->searchable()->sortable(),
            TextColumn::make('point')->label('Titik')->searchable(),
            TextColumn::make('rt.name')->label('RT')->placeholder('RW'),
            TextColumn::make('starts_at')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('ends_at')->label('Selesai')->dateTime('d M Y H:i'),
            TextColumn::make('capacity')->label('Kapasitas'),
            TextColumn::make('served_count')->label('Terlayani'),
            TextColumn::make('status')->label('Status')->badge(),
        ])->recordActions([
            EditAction::make()->modalWidth(Width::SevenExtraLarge)->visible(fn (MobileService $record): bool => $record->status === MobileServiceStatus::Draft)->fillForm(fn (MobileService $record): array => [
                'staff_ids' => $record->staff()->pluck('users.id')->all(),
                'waste_type_ids' => $record->wasteTypes()->pluck('waste_types.id')->all(),
            ])->using(fn (MobileService $record, array $data): MobileService => self::service()->update(self::actor(), $record, isset($data['rw_id']) ? (int) $data['rw_id'] : null, isset($data['rt_id']) ? (int) $data['rt_id'] : null, (string) $data['point'], (string) $data['starts_at'], (string) $data['ends_at'], (int) $data['capacity'], (string) ($data['notes'] ?? ''), array_map('intval', $data['staff_ids'] ?? []), array_map('intval', $data['waste_type_ids'] ?? []))),
            Action::make('publish')->label('Publikasikan')->icon(Heroicon::OutlinedMegaphone)->color('success')->visible(fn (MobileService $record): bool => $record->status === MobileServiceStatus::Draft)->authorize('publish')->requiresConfirmation()->modalHeading(fn (MobileService $record): string => "Publikasikan layanan {$record->service_number}?")->modalDescription('Jadwal dan titik layanan akan terlihat oleh warga sesuai cakupan yang dipilih.')->modalSubmitActionLabel('Publikasikan layanan')->action(function (MobileService $record): void {
                try {
                    self::service()->transition(self::actor(), $record, MobileServiceStatus::Published);
                } catch (ValidationException) {
                    Notification::make()->title('Layanan belum dapat dipublikasikan')->body('Periksa jadwal dan penugasan petugas, lalu coba publikasikan kembali.')->danger()->send();
                }
            }),
            Action::make('open')->label('Buka titik')->icon(Heroicon::OutlinedPlay)->color('success')->visible(fn (MobileService $record): bool => $record->status === MobileServiceStatus::Published)->authorize('operate')->requiresConfirmation()->modalHeading(fn (MobileService $record): string => "Buka titik layanan {$record->service_number}?")->modalDescription('Titik layanan mulai menerima setoran selama jadwal dan kapasitas masih tersedia.')->modalSubmitActionLabel('Buka titik layanan')->action(fn (MobileService $record): MobileService => self::service()->transition(self::actor(), $record, MobileServiceStatus::Open)),
            Action::make('close')->label('Tutup titik')->icon(Heroicon::OutlinedStop)->color('warning')->visible(fn (MobileService $record): bool => $record->status === MobileServiceStatus::Open)->authorize('operate')->requiresConfirmation()->modalHeading(fn (MobileService $record): string => "Tutup layanan {$record->service_number}?")->modalDescription('Titik layanan tidak menerima transaksi baru setelah ditutup.')->modalSubmitActionLabel('Tutup layanan')->action(fn (MobileService $record): MobileService => self::service()->transition(self::actor(), $record, MobileServiceStatus::Closed)),
            Action::make('cancel')->label('Batalkan')->icon(Heroicon::OutlinedXCircle)->color('danger')->visible(fn (MobileService $record): bool => in_array($record->status, [MobileServiceStatus::Draft, MobileServiceStatus::Published], true))->authorize('cancel')->requiresConfirmation()->modalHeading(fn (MobileService $record): string => "Batalkan layanan {$record->service_number}?")->modalDescription('Jadwal layanan tidak dapat dibuka untuk transaksi baru.')->modalSubmitActionLabel('Batalkan layanan')->action(fn (MobileService $record): MobileService => self::service()->transition(self::actor(), $record, MobileServiceStatus::Cancelled)),
        ]);
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('viewAny', MobileService::class);
    }

    /** @return Builder<MobileService> */
    public static function getEloquentQuery(): Builder
    {
        return MobileService::query()->with(['rt', 'rw', 'staff', 'wasteTypes']);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageMobileServices::route('/')];
    }

    private static function service(): MobileServiceService
    {
        return app(MobileServiceService::class);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
