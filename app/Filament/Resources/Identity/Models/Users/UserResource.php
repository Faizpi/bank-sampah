<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Users;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Actions\ChangeUserRole;
use App\Domain\Identity\Actions\ManageStaffProfile;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Enums\UserRole;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Filament\Resources\Identity\Models\Users\Pages\ManageUsers as ManageUsersPage;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use UnitEnum;

final class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Direktori';

    protected static ?int $navigationSort = 20;

    protected static ?string $navigationLabel = 'Pengguna';

    protected static ?string $modelLabel = 'pengguna';

    protected static ?string $pluralModelLabel = 'pengguna';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'user.view');
    }

    public static function canCreate(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && app(PermissionChecker::class)->allows($actor, 'user.create');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Akun pengguna')->schema([
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('username')->label('Username')->required()->minLength(3)->maxLength(60)->regex('/^[a-z0-9._-]+$/')->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Nomor telepon (opsional)')->nullable()->regex('/^62[0-9]{8,16}$/')->maxLength(20),
                TextInput::make('email')->label('Email')->email()->nullable(),
                TextInput::make('password')->label('Kata sandi')->visibleOn('create')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->minLength(8)->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('password_confirmation')->label('Konfirmasi kata sandi')->visibleOn('create')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->same('password')->dehydrated(fn (string $operation): bool => $operation === 'create'),
                Select::make('role')->label('Peran')->options(fn (): array => UserRole::options(true))->required(fn (string $operation): bool => $operation === 'create')->visibleOn('create')->dehydrated(fn (string $operation): bool => $operation === 'create'),
            ])->columns(['default' => 1, 'md' => 2]),
            Section::make('Area dan masa tugas')->visible(fn (?User $record): bool => $record !== null && $record->hasRole('petugas', 'bendahara'))->schema([
                Repeater::make('service_areas')->label('Penugasan')->defaultItems(0)->minItems(1)->required()->schema([
                    Select::make('service_area_id')->label('Area layanan')->options(fn (): array => ServiceArea::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->required()->distinct(),
                    DatePicker::make('active_from')->label('Mulai bertugas'),
                    DatePicker::make('active_to')->label('Akhir tugas')->afterOrEqual('active_from'),
                ])->columns(['default' => 1, 'md' => 3]),
            ]),
            Section::make('Perubahan peran')->visible(fn (?User $record): bool => $record !== null && app(PermissionChecker::class)->allows(self::actor(), 'role.manage'))->schema([
                Select::make('new_role')->label('Peran baru (opsional)')->placeholder('Tetap menggunakan peran saat ini')->options(fn (): array => UserRole::options(true))->live(),
                Textarea::make('role_reason')->label('Alasan perubahan peran')->minLength(10)->maxLength(1000)->requiredWith('new_role'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('status')->label('Status')->badge(),
                TextColumn::make('customerProfile.customer_number')->label('Nomor nasabah')->placeholder('Belum terbit')->toggleable(),
                TextColumn::make('role')->label('Peran')->badge()->separator(','),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat')->modalWidth(Width::FiveExtraLarge)->authorize('manageView')->schema([
                    TextInput::make('name')->label('Nama'),
                    TextInput::make('username')->label('Username'),
                    TextInput::make('status')->label('Status'),
                    TextInput::make('phone')->label('Nomor telepon')->placeholder('Tidak tersedia'),
                    TextInput::make('email')->label('Email')->placeholder('Tidak tersedia'),
                    TextInput::make('role_label')->label('Peran')->placeholder('Belum diatur'),
                    TextInput::make('customer_number')->label('Nomor nasabah')->placeholder('Belum terbit'),
                    TextInput::make('staff_number')->label('Nomor petugas')->placeholder('Belum dibuat'),
                    TextInput::make('service_area_names')->label('Area pelayanan')->placeholder('Belum diatur'),
                ])->fillForm(function (User $record): array {
                    $record->loadMissing(['customerProfile.rt.rw.dusun', 'staffProfile.serviceAreas.serviceArea']);

                    return [
                        'name' => $record->name,
                        'username' => $record->username,
                        'status' => $record->status->value,
                        'phone' => $record->phone,
                        'email' => $record->email,
                        'role_label' => $record->role->value,
                        'customer_number' => $record->customerProfile?->customer_number,
                        'staff_number' => $record->staffProfile?->staff_number,
                        'service_area_names' => $record->customerProfile?->serviceRegion()
                            ?? $record->staffProfile?->serviceAreas
                                ->map(fn ($assignment): string => $assignment->serviceArea->name)
                                ->filter()
                                ->implode(', ') ?: null,
                    ];
                }),
                EditAction::make()->label('Kelola akun dan tugas')->modalWidth(Width::SevenExtraLarge)->authorize('manageUpdate')
                    ->mutateRecordDataUsing(fn (array $data, User $record): array => [...$data, 'name' => $record->name, 'username' => $record->username, 'phone' => $record->phone, 'email' => $record->email, 'service_areas' => $record->staffProfile?->serviceAreas->map(fn ($assignment): array => [
                        'service_area_id' => $assignment->service_area_id, 'active_from' => $assignment->active_from?->toDateString(), 'active_to' => $assignment->active_to?->toDateString(),
                    ])->all() ?? [], 'new_role' => null])
                    ->using(function (User $record, array $data): User {
                        return DB::transaction(function () use ($record, $data): User {
                            $user = app(ManageUsers::class)->update(self::actor(), $record, $data);
                            if (! empty($data['new_role'])) {
                                $user = app(ChangeUserRole::class)->handle(self::actor(), $user, (string) $data['new_role'], (string) ($data['role_reason'] ?? ''));
                            }
                            if (array_key_exists('service_areas', $data) && $user->hasRole('petugas', 'bendahara')) {
                                $user = app(ManageStaffProfile::class)->save(self::actor(), $user, $data);
                            }

                            return $user;
                        });
                    }),
                Action::make('activate')->label('Aktifkan pengguna')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->visible(fn (User $record): bool => $record->status === UserStatus::Inactive)->requiresConfirmation()->modalHeading(fn (User $record): string => "Aktifkan pengguna {$record->name}?")->modalDescription('Pengguna dapat masuk kembali dan menggunakan izin yang masih dimilikinya.')->modalSubmitActionLabel('Aktifkan pengguna')->action(fn (User $record): User => app(ManageUsers::class)->activate(self::actor(), $record))->successNotificationTitle('Pengguna diaktifkan.'),
                Action::make('deactivate')->label('Nonaktifkan pengguna')->icon(Heroicon::OutlinedNoSymbol)->color('danger')->authorize('deactivate')->visible(fn (User $record): bool => $record->status === UserStatus::Active)->requiresConfirmation()->modalHeading(fn (User $record): string => "Nonaktifkan pengguna {$record->name}?")->modalDescription('Pengguna tidak dapat masuk atau menjalankan tugas baru. Riwayat dan data transaksi tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan pengguna')->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(3)])->action(fn (User $record, array $data): User => app(ManageUsers::class)->deactivate(self::actor(), $record, (string) $data['reason']))->successNotificationTitle('Pengguna dinonaktifkan.'),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageUsersPage::route('/')];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return User::query()->whereKey([]);
        }

        return app(VisibleUsers::class)->queryFor($actor, ...UserStatus::cases())
            ->with(['customerProfile.rt.rw.dusun', 'staffProfile.serviceArea', 'staffProfile.serviceAreas.serviceArea'])
            ->orderByRaw("CASE role WHEN 'admin' THEN 1 WHEN 'bendahara' THEN 2 WHEN 'petugas' THEN 3 ELSE 4 END")
            ->orderBy('name');
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
