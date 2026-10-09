<?php

declare(strict_types=1);

namespace App\Filament\Resources\WasteMaster\Models\WasteTypes;

use App\Domain\WasteMaster\Actions\ManageWasteMaster;
use App\Domain\WasteMaster\Actions\SaveWasteCatalogType;
use App\Domain\WasteMaster\Actions\SaveWasteTypePhoto;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use App\Filament\Resources\WasteMaster\Models\WasteTypes\Pages\ManageWasteTypes;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use UnitEnum;

final class WasteTypeResource extends Resource
{
    protected static ?string $model = WasteType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Katalog Sampah';

    protected static ?int $navigationSort = 90;

    protected static ?string $navigationLabel = 'Jenis Sampah';

    protected static ?string $modelLabel = 'jenis sampah';

    protected static ?string $pluralModelLabel = 'jenis sampah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas jenis')->schema([
                TextInput::make('code')->label('Kode')->required()->maxLength(30)->regex('/^[A-Za-z0-9_-]+$/')->unique(ignoreRecord: true),
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                Select::make('waste_category_id')->label('Kategori')->relationship('category', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->saveRelationshipsUsing(static fn (): bool => false)->dehydrated()->required()->searchable()->preload(),
                Select::make('waste_unit_id')->label('Satuan')->relationship('unit', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->saveRelationshipsUsing(static fn (): bool => false)->dehydrated()->required()->searchable()->preload(),
                Select::make('condition_ids')->label('Kondisi diterima')->relationship('conditions', 'name', modifyQueryUsing: fn ($query) => $query->where('is_active', true))->saveRelationshipsUsing(static fn (): bool => false)->dehydrated()->multiple()->required()->minItems(1)->searchable()->preload(),
                TextInput::make('sort_order')->label('Urutan')->numeric()->integer()->minValue(0)->maxValue(9999)->default(0)->required(),
                Checkbox::make('is_plastic')->label('Kelompok plastik'),
                Checkbox::make('is_active')->label('Aktif')->default(true),
                Textarea::make('education_description')->label('Penjelasan untuk warga')->maxLength(5000)->rows(5)->columnSpanFull(),
                View::make('filament.backoffice.waste-photo-preview')->viewData(fn (?WasteType $record): array => ['photoUrl' => $record === null ? null : app(PublicWasteCatalog::class)->photoUrl($record)])
                    ->visible(fn (?WasteType $record): bool => $record?->publicPhoto !== null)->columnSpanFull(),
                FileUpload::make('photo')->label('Foto jenis sampah')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])
                    ->maxSize(5120)->storeFiles(false)->required(fn (?WasteType $record): bool => $record === null)
                    ->helperText('Foto JPG/PNG maksimal 5 MB. Tampil di landing page, katalog, dan harga. Jangan unggah foto orang atau bukti transaksi. Kosongkan saat edit untuk mempertahankan foto lama.')
                    ->columnSpanFull(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->columns([
            ImageColumn::make('catalog_photo')->label('Foto')->getStateUsing(fn (WasteType $record): ?string => app(PublicWasteCatalog::class)->photoUrl($record))->placeholder('Belum diunggah')
                ->extraImgAttributes(fn (WasteType $record): array => ['alt' => 'Foto '.$record->name, 'loading' => 'lazy'])->square()->imageSize(56),
            TextColumn::make('code')->label('Kode')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable()->sortable(),
            TextColumn::make('category.name')->label('Kategori')->sortable(),
            TextColumn::make('unit.symbol')->label('Satuan'),
            TextColumn::make('conditions.name')->label('Kondisi'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
        ])->recordActions([
            Action::make('photo')->label('Foto')->icon(Heroicon::OutlinedPhoto)->authorize('update')
                ->modalHeading(fn (WasteType $record): string => 'Foto '.$record->name)
                ->modalSubmitActionLabel('Simpan foto')
                ->schema([
                    View::make('filament.backoffice.waste-photo-preview')->viewData(fn (WasteType $record): array => ['photoUrl' => app(PublicWasteCatalog::class)->photoUrl($record)]),
                    FileUpload::make('photo')->label('Foto baru')->image()->acceptedFileTypes(['image/jpeg', 'image/png'])->maxSize(5120)->storeFiles(false)->required()
                        ->helperText('Foto JPG/PNG maksimal 5 MB dan 20 megapiksel. Tampil publik; gunakan foto sampah tanpa orang atau bukti transaksi.'),
                ])->action(function (WasteType $record, array $data): void {
                    $photo = $data['photo'] ?? null;
                    if (! $photo instanceof UploadedFile) {
                        throw ValidationException::withMessages(['photo' => 'Pilih foto JPG/PNG yang sudah selesai diunggah.']);
                    }
                    app(SaveWasteTypePhoto::class)->handle(auth()->user(), $record, $photo);
                }),
            EditAction::make()->using(fn (WasteType $record, array $data): WasteType => app(SaveWasteCatalogType::class)->handle(auth()->user(), $record, $data)),
            Action::make('activate')->label('Aktifkan kembali')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->requiresConfirmation()->modalHeading(fn (WasteType $record): string => "Aktifkan jenis {$record->name}?")->modalDescription('Jenis ini kembali tersedia untuk transaksi baru. Kategori dan kondisi yang dipakai harus aktif.')->modalSubmitActionLabel('Aktifkan jenis')->visible(fn (WasteType $record): bool => ! $record->is_active)->action(function (WasteType $record): void {
                try {
                    app(ManageWasteMaster::class)->activate(auth()->user(), $record);
                } catch (ValidationException) {
                    Notification::make()->title('Jenis sampah belum dapat diaktifkan')->body('Pastikan kategori, satuan, dan seluruh kondisi penerimaan aktif, lalu coba kembali.')->danger()->send();
                }
            }),
            Action::make('deactivate')->label('Nonaktifkan')->authorize('deactivate')->requiresConfirmation()->modalHeading(fn (WasteType $record): string => "Nonaktifkan jenis {$record->name}?")->modalDescription('Jenis ini tidak tersedia untuk transaksi baru. Data transaksi lama tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan jenis')->visible(fn (WasteType $record): bool => $record->is_active)->action(fn (WasteType $record) => app(ManageWasteMaster::class)->deactivate(auth()->user(), $record)),
        ])->stackedOnMobile();
    }

    /** @return Builder<WasteType> */
    public static function getEloquentQuery(): Builder
    {
        return WasteType::query()->with('publicPhoto');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageWasteTypes::route('/')];
    }
}
