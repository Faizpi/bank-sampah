<?php

declare(strict_types=1);

namespace App\Filament\Resources\Statistics\Models\StatisticPublications;

use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\Statistics\Services\StatisticsService;
use App\Filament\Resources\Statistics\Models\StatisticPublications\Pages\ManageStatisticPublications;
use App\Models\User;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class StatisticPublicationResource extends Resource
{
    protected static ?string $model = StatisticPublication::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBar;

    protected static string|UnitEnum|null $navigationGroup = 'Program';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Statistik Publik';

    protected static ?string $modelLabel = 'publikasi statistik';

    protected static ?string $pluralModelLabel = 'publikasi statistik';

    protected static ?string $recordTitleAttribute = 'publication_key';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Allowlist publikasi')->schema([
                TextInput::make('publication_key')->label('Kunci publikasi')->disabled()->dehydrated(false),
                CheckboxList::make('metrics')->label('Metrik')->options(self::metrics())->required()->minItems(1),
                CheckboxList::make('dimensions')->label('Dimensi')->options(['period' => 'Periode', 'rt_id' => 'RT'])->required()->minItems(1),
                TextInput::make('privacy_threshold')->label('Batas minimum jumlah warga')->numeric()->integer()->minValue(2)->maxValue(1000)->required(),
                Toggle::make('is_active')->label('Aktif'),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('publication_key')->columns([
            TextColumn::make('publication_key')->label('Kunci')->searchable(),
            TextColumn::make('metrics')->label('Metrik')->formatStateUsing(fn ($state): string => is_array($state) ? implode(', ', $state) : ''),
            TextColumn::make('privacy_threshold')->label('Ambang privasi'),
            IconColumn::make('is_active')->label('Aktif')->boolean(),
            TextColumn::make('approver.name')->label('Disetujui oleh')->placeholder('Belum disetujui'),
        ])->recordActions([
            EditAction::make()->using(fn (StatisticPublication $record, array $data): StatisticPublication => self::service()->configurePublic(self::actor(), array_values(array_map('strval', $data['metrics'] ?? [])), array_values(array_map('strval', $data['dimensions'] ?? [])), (int) $data['privacy_threshold'], (bool) ($data['is_active'] ?? false))),
        ]);
    }

    /** @return array<string, string> */
    private static function metrics(): array
    {
        return ['active_customers' => 'Nasabah aktif', 'deposit_count' => 'Jumlah setoran', 'total_weight_kg' => 'Total berat', 'plastic_weight_kg' => 'Berat plastik', 'dominant_waste_type' => 'Jenis dominan', 'target_progress_kg' => 'Progres target', 'mobile_service_count' => 'Jumlah layanan keliling'];
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('viewAny', StatisticPublication::class);
    }

    /** @return Builder<StatisticPublication> */
    public static function getEloquentQuery(): Builder
    {
        return StatisticPublication::query()->with('approver');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageStatisticPublications::route('/')];
    }

    private static function service(): StatisticsService
    {
        return app(StatisticsService::class);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
