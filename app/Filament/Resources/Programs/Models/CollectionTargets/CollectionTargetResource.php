<?php

declare(strict_types=1);

namespace App\Filament\Resources\Programs\Models\CollectionTargets;

use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Programs\Enums\TargetStatus;
use App\Domain\Programs\Models\CollectionTarget;
use App\Domain\Programs\Services\TargetProgressService;
use App\Domain\Programs\Services\TargetService;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteType;
use App\Filament\Resources\Programs\Models\CollectionTargets\Pages\ManageCollectionTargets;
use App\Models\User;
use App\Support\WeightFormatter;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class CollectionTargetResource extends Resource
{
    protected static ?string $model = CollectionTarget::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedFlag;

    protected static string|UnitEnum|null $navigationGroup = 'Program';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Target Pengumpulan';

    protected static ?string $modelLabel = 'target';

    protected static ?string $pluralModelLabel = 'target';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Definisi target')->schema([
                TextInput::make('name')->label('Nama')->required()->minLength(3)->maxLength(160),
                Textarea::make('purpose')->label('Tujuan')->required()->minLength(3)->maxLength(2000)->rows(5),
                DatePicker::make('period_start')->label('Mulai')->required(),
                DatePicker::make('period_end')->label('Selesai')->required()->after('period_start'),
                TextInput::make('target_weight_kg')->label('Target berat (kg)')->numeric()->required()->minValue(0.001),
                Toggle::make('is_public')->label('Tampilkan ke publik'),
                Repeater::make('scopes')->label('Cakupan target')->schema([
                    Select::make('waste_type_id')->label('Jenis')->options(fn (): array => WasteType::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    Select::make('waste_category_id')->label('Kategori')->options(fn (): array => WasteCategory::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                    Select::make('rt_id')->label('RT')->options(fn (): array => Rt::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->searchable(),
                ])->columns(3)->defaultItems(0),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('name')->defaultSort('period_start', 'desc')->columns([
            TextColumn::make('target_number')->label('Nomor')->searchable()->sortable(),
            TextColumn::make('name')->label('Nama')->searchable(),
            TextColumn::make('period_start')->label('Mulai')->date('d M Y')->sortable(),
            TextColumn::make('period_end')->label('Selesai')->date('d M Y')->sortable(),
            TextColumn::make('target_weight_kg')->label('Target')->formatStateUsing(fn (?string $state): string => WeightFormatter::format($state))->suffix(' kg'),
            TextColumn::make('progress')->label('Progres')->formatStateUsing(fn (?string $state): string => WeightFormatter::format($state))->suffix(' kg')->state(fn (CollectionTarget $record): string => app(TargetProgressService::class)->progress($record)),
            TextColumn::make('status')->label('Status')->badge(),
        ])->recordActions([
            EditAction::make()->visible(fn (CollectionTarget $record): bool => $record->status === TargetStatus::Draft)->using(fn (CollectionTarget $record, array $data): CollectionTarget => self::service()->update(self::actor(), $record, (string) $data['name'], (string) $data['purpose'], (string) $data['period_start'], (string) $data['period_end'], (string) $data['target_weight_kg'], (bool) ($data['is_public'] ?? false), $data['scopes'] ?? [])),
            Action::make('activate')->label('Terbitkan target')->icon(Heroicon::OutlinedPlay)->color('success')->visible(fn (CollectionTarget $record): bool => $record->status === TargetStatus::Draft)->authorize('activate')->requiresConfirmation()->modalHeading(fn (CollectionTarget $record): string => "Terbitkan target {$record->name}?")->modalDescription('Target akan mulai menerima progres sesuai periode dan cakupan yang ditetapkan.')->modalSubmitActionLabel('Terbitkan target')->action(fn (CollectionTarget $record): CollectionTarget => self::service()->activate(self::actor(), $record)),
            Action::make('close')->label('Tutup')->icon(Heroicon::OutlinedStop)->color('warning')->visible(fn (CollectionTarget $record): bool => $record->status === TargetStatus::Active)->authorize('close')->requiresConfirmation()->modalHeading(fn (CollectionTarget $record): string => "Tutup target {$record->name}?")->modalDescription('Target tidak lagi menerima progres baru setelah periode ditutup.')->modalSubmitActionLabel('Tutup target')->action(fn (CollectionTarget $record): CollectionTarget => self::service()->close(self::actor(), $record)),
            Action::make('cancel')->label('Batalkan')->icon(Heroicon::OutlinedXCircle)->color('danger')->visible(fn (CollectionTarget $record): bool => in_array($record->status, [TargetStatus::Draft, TargetStatus::Active], true))->authorize('cancel')->requiresConfirmation()->modalHeading(fn (CollectionTarget $record): string => "Batalkan target {$record->name}?")->modalDescription('Target tidak dapat diterbitkan atau dilanjutkan kembali.')->modalSubmitActionLabel('Batalkan target')->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(4)])->action(fn (CollectionTarget $record, array $data): CollectionTarget => self::service()->cancel(self::actor(), $record, (string) $data['reason'])),
        ]);
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('viewAny', CollectionTarget::class);
    }

    /** @return Builder<CollectionTarget> */
    public static function getEloquentQuery(): Builder
    {
        return self::service()->visibleQuery(self::actor())->with('scopes');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageCollectionTargets::route('/')];
    }

    private static function service(): TargetService
    {
        return app(TargetService::class);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
