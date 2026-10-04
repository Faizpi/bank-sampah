<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\CitizenVerifications;

use App\Actions\Auth\ResolveCitizenVerification;
use App\Domain\Identity\Enums\UserStatus;
use App\Filament\Resources\Identity\Models\CitizenVerifications\Pages\ManageCitizenVerifications;
use App\Models\User;
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
use Illuminate\Support\Str;
use UnitEnum;

final class CitizenVerificationResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Verifikasi Warga';

    protected static ?string $modelLabel = 'verifikasi warga';

    protected static ?string $pluralModelLabel = 'verifikasi warga';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable(),
                TextColumn::make('customerProfile.customer_number')->label('Nomor nasabah')->searchable(),
                TextColumn::make('phone')->label('Telepon')->searchable(),
                TextColumn::make('created_at')->label('Didaftarkan')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('verify')
                    ->label('Verifikasi')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->authorize('verify')
                    ->requiresConfirmation()
                    ->modalHeading('Verifikasi warga')
                    ->modalDescription('Warga akan diaktifkan dan dapat menggunakan layanan.')
                    ->modalSubmitActionLabel('Verifikasi warga')
                    ->successNotificationTitle('Warga berhasil diverifikasi.')
                    ->action(fn (User $record): User => app(ResolveCitizenVerification::class)->verify(self::actor(), $record, self::correlationId())),
                Action::make('reject')
                    ->label('Tolak')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->authorize('reject')
                    ->requiresConfirmation()
                    ->modalHeading('Tolak verifikasi warga')
                    ->modalDescription('Penolakan tidak dapat dibatalkan dari antrean ini.')
                    ->modalSubmitActionLabel('Tolak verifikasi')
                    ->schema([
                        Textarea::make('rejection_reason')
                            ->label('Alasan penolakan')
                            ->required()
                            ->maxLength(500)
                            ->rows(4),
                    ])
                    ->successNotificationTitle('Verifikasi warga ditolak.')
                    ->action(fn (User $record, array $data): User => app(ResolveCitizenVerification::class)->reject(self::actor(), $record, $data['rejection_reason'], self::correlationId())),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageCitizenVerifications::route('/')];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        return User::query()
            ->where('status', UserStatus::PendingVerification)
            ->whereHas('customerProfile');
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }

    private static function correlationId(): string
    {
        $correlationId = request()->attributes->get('correlation_id');

        return is_string($correlationId) ? $correlationId : (string) Str::uuid();
    }
}
