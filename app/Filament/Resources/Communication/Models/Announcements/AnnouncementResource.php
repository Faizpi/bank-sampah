<?php

declare(strict_types=1);

namespace App\Filament\Resources\Communication\Models\Announcements;

use App\Domain\Communication\Enums\AnnouncementStatus;
use App\Domain\Communication\Models\Announcement;
use App\Domain\Communication\Services\AnnouncementService;
use App\Filament\Resources\Communication\Models\Announcements\Pages\ManageAnnouncements;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;
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
            Section::make('Isi pengumuman publik')->schema([
                TextInput::make('title')->label('Judul')->required()->minLength(3)->maxLength(160),
                Textarea::make('body')->label('Isi')->required()->minLength(3)->maxLength(10000)->rows(8),
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
            TextColumn::make('publish_start')->label('Mulai')->dateTime('d M Y H:i')->sortable(),
            TextColumn::make('publish_end')->label('Berakhir')->dateTime('d M Y H:i')->placeholder('Tanpa batas'),
            TextColumn::make('status')->label('Status')->badge(),
        ])->recordActions([
            EditAction::make()->visible(fn (Announcement $record): bool => in_array($record->status->value, ['draf', 'nonaktif'], true))->using(fn (Announcement $record, array $data): Announcement => self::service()->update(self::actor(), $record, (string) $data['title'], (string) $data['body'], (string) $data['publish_start'], $data['publish_end'] ?? null, (int) $data['priority'])),
            Action::make('publish')->label('Terbitkan')->icon(Heroicon::OutlinedMegaphone)->color('success')->visible(fn (Announcement $record): bool => in_array($record->status->value, ['draf', 'nonaktif'], true))->authorize('publish')->requiresConfirmation()->modalHeading(fn (Announcement $record): string => "Terbitkan pengumuman {$record->title}?")->modalDescription('Pengumuman akan tampil kepada publik sesuai periode tayang.')->modalSubmitActionLabel('Terbitkan pengumuman')->action(function (Announcement $record): void {
                try {
                    self::service()->publish(self::actor(), $record);
                } catch (ValidationException $exception) {
                    self::validationNotification('Pengumuman belum dapat diterbitkan', $exception);
                }
            }),
            Action::make('unpublish')->label('Nonaktifkan')->icon(Heroicon::OutlinedEyeSlash)->color('warning')->visible(fn (Announcement $record): bool => $record->status === AnnouncementStatus::Published)->authorize('publish')->requiresConfirmation()->modalHeading(fn (Announcement $record): string => "Nonaktifkan pengumuman {$record->title}?")->modalDescription('Pengumuman tidak lagi tampil kepada publik. Data historis tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan pengumuman')->action(function (Announcement $record): void {
                try {
                    self::service()->unpublish(self::actor(), $record);
                } catch (ValidationException $exception) {
                    self::validationNotification('Pengumuman belum dapat dinonaktifkan', $exception);
                }
            }),
            Action::make('delete')->label('Hapus')->icon(Heroicon::OutlinedTrash)->color('danger')->authorize('delete')->requiresConfirmation()->modalHeading(fn (Announcement $record): string => "Hapus pengumuman {$record->title}?")->modalDescription('Pengumuman akan dihapus dari daftar dan publik, tetapi catatan audit tetap tersimpan.')->modalSubmitActionLabel('Hapus pengumuman')->action(function (Announcement $record): void {
                self::service()->delete(self::actor(), $record);
            }),
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

    private static function validationNotification(string $title, ValidationException $exception): void
    {
        Notification::make()->title($title)->body(implode(' ', $exception->validator->errors()->all()))->danger()->send();
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
