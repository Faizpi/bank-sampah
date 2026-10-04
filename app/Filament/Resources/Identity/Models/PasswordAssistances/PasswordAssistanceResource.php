<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\PasswordAssistances;

use App\Actions\Auth\ChangePassword;
use App\Authorization\PermissionChecker;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Filament\Resources\Identity\Models\PasswordAssistances\Pages\ManagePasswordAssistances;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use UnitEnum;

final class PasswordAssistanceResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Keamanan & Akses';

    protected static ?int $navigationSort = 40;

    protected static ?string $navigationLabel = 'Bantuan Kata Sandi';

    protected static ?string $modelLabel = 'bantuan kata sandi';

    protected static ?string $pluralModelLabel = 'bantuan kata sandi';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(PermissionChecker::class)->allows($actor, 'user.view')
            && app(PermissionChecker::class)->allows($actor, 'user.reset-password')
            && app(PermissionChecker::class)->allows($actor, 'session.revoke');
    }

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
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('customerProfile.customer_number')->label('Nomor nasabah')->toggleable(),
            ])
            ->recordActions([
                Action::make('changePassword')
                    ->label('Ubah kata sandi')
                    ->icon(Heroicon::OutlinedKey)
                    ->authorize(fn (User $record): bool => auth()->user() instanceof User
                        && auth()->user()->can('resetPassword', $record)
                        && auth()->user()->can('revokeSession', $record))
                    ->requiresConfirmation()
                    ->modalHeading('Bantuan kata sandi')
                    ->modalDescription('Kata sandi akan diganti dan semua sesi aktif pengguna diakhiri.')
                    ->modalSubmitActionLabel('Atur ulang kata sandi')
                    ->schema([
                        Select::make('verification_method')
                            ->label('Metode verifikasi')
                            ->options([
                                'tatap_muka' => 'Tatap muka',
                                'callback_nomor_terdaftar' => 'Callback nomor terdaftar',
                            ])
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->minLength(10)
                            ->maxLength(1000)
                            ->rows(4),
                        TextInput::make('password')
                            ->label('Kata sandi baru')
                            ->password()
                            ->autocomplete('new-password')
                            ->required()
                            ->minLength(10),
                        TextInput::make('password_confirmation')
                            ->label('Konfirmasi kata sandi baru')
                            ->password()
                            ->autocomplete('new-password')
                            ->same('password')
                            ->required(),
                    ])
                    ->successNotificationTitle('Kata sandi pengguna diubah dan semua sesinya diakhiri.')
                    ->action(fn (User $record, array $data): User => app(ChangePassword::class)->directAdmin(
                        self::actor(),
                        $record,
                        $data['verification_method'],
                        $data['reason'],
                        $data['password'],
                        $data['password_confirmation'],
                        self::correlationId(),
                    )),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePasswordAssistances::route('/')];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $actor = self::actor();

        return app(VisibleUsers::class)
            ->queryFor($actor, UserStatus::Active, UserStatus::PendingVerification)
            ->select(['id', 'name', 'status']);
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

        return is_string($correlationId) && Str::isUuid($correlationId)
            ? strtolower($correlationId)
            : (string) Str::uuid();
    }
}
