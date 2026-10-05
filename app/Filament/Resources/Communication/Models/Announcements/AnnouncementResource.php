<?php

declare(strict_types=1);

namespace App\Filament\Resources\Communication\Models\Announcements;

use App\Domain\Communication\Enums\AnnouncementAudience;
use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Services\AnnouncementService;
use App\Domain\CustomersRegions\Models\Rt;
use App\Filament\Resources\Communication\Models\Announcements\Pages\ManageAnnouncements;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

final class AnnouncementResource extends Resource
{
    protected static ?string $model = Announcement::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedMegaphone;

    protected static string|UnitEnum|null $navigationGroup = 'Program';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Pengumuman';

    protected static ?string $modelLabel = 'pengumuman';

    protected static ?string $pluralModelLabel = 'pengumuman';

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Isi dan audiens')->schema([
                TextInput::make('title')->label('Judul')->required()->minLength(3)->maxLength(160),
                Textarea::make('body')->label('Isi')->required()->minLength(3)->maxLength(10000)->rows(8),
                Select::make('audience')->label('Audiens')->options(collect(AnnouncementAudience::cases())->mapWithKeys(fn (AnnouncementAudience $case): array => [$case->value => ucfirst($case->value)])->all())->required(),
                Select::make('rt_ids')->label('RT sasaran')->multiple()->searchable()->preload()->options(fn (): array => Rt::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->visible(fn ($get): bool => $get('audience') === AnnouncementAudience::Region->value),
                DateTimePicker::make('publish_start')->label('Mulai tampil')->seconds(false)->native(false)->required(),
                DateTimePicker::make('publish_end')->label('Berakhir tampil')->seconds(false)->native(false)->after('publish_start'),
                TextInput::make('priority')->label('Prioritas')->numeric()->integer()->minValue(0)->maxValue(1000)->default(0)->required(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->recordTitleAttribute('title')->defaultSort('publish_start', 'desc')->columns([
            TextColumn::make('announcement_number')->label('Nomor')->searchable()->sortable(),
            TextColumn::make('title')->label('Judul')->searchable(),
            TextColumn::make('audience')->label('Audiens')->badge(),
            TextColumn::make('publish_start')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('publish_end')->label('Berakhir')->dateTime('d M Y H:i')->placeholder('Tanpa batas'),
            TextColumn::make('status')->label('Status')->badge(),
        ])->recordActions([
            EditAction::make()->visible(fn (Announcement $record): bool => in_array($record->status->value, ['draf', 'nonaktif'], true))->fillForm(fn (Announcement $record): array => [
                'rt_ids' => $record->rts()->pluck('rt.id')->all(),
            ])->using(fn (Announcement $record, array $data): Announcement => self::service()->update(self::actor(), $record, (string) $data['title'], (string) $data['body'], (string) $data['audience'], (string) $data['publish_start'], $data['publish_end'] ?? null, array_map('intval', $data['rt_ids'] ?? []), (int) $data['priority'])),
            Action::make('publish')->label('Terbitkan')->icon(Heroicon::OutlinedMegaphone)->color('success')->visible(fn (Announcement $record): bool => in_array($record->status->value, ['draf', 'nonaktif'], true))->authorize('publish')->requiresConfirmation()->modalHeading(fn (Announcement $record): string => "Terbitkan pengumuman {$record->title}?")->modalDescription('Pengumuman akan tampil kepada audiens yang dipilih sesuai periode tayang.')->modalSubmitActionLabel('Terbitkan pengumuman')->action(fn (Announcement $record): Announcement => self::service()->publish(self::actor(), $record)),
            Action::make('unpublish')->label('Nonaktifkan')->icon(Heroicon::OutlinedEyeSlash)->color('warning')->visible(fn (Announcement $record): bool => $record->status === AnnouncementStatus::Published)->authorize('publish')->requiresConfirmation()->modalHeading(fn (Announcement $record): string => "Nonaktifkan pengumuman {$record->title}?")->modalDescription('Pengumuman tidak lagi tampil kepada audiens. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan pengumuman')->action(fn (Announcement $record): Announcement => self::service()->unpublish(self::actor(), $record)),
        ]);
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->can('viewAny', Announcement::class);
    }

    /** @return Builder<Announcement> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return Announcement::query()->whereRaw('1 = 0')->with('rts');
        }

        return self::service()->visibleQuery($actor)->with('rts');
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageAnnouncements::route('/')];
    }

    private static function service(): AnnouncementService
    {
        return app(AnnouncementService::class);
    }

    private static function actor(): User
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            throw new \LogicException('Announcement resource requires an authenticated user.');
        }

        return $actor;
    }
}
