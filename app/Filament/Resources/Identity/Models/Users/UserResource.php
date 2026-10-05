<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Users;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Actions\ManageRoles;
use App\Domain\Identity\Actions\ManageStaffProfile;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\Role;
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
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
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
                TextInput::make('phone')->label('Nomor telepon')->required()->regex('/^62[0-9]{8,16}$/')->maxLength(20),
                TextInput::make('email')->label('Email')->email()->nullable(),
                TextInput::make('password')->label('Kata sandi')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->minLength(10)->dehydrated(fn (?string $state): bool => filled($state)),
                TextInput::make('password_confirmation')->label('Konfirmasi kata sandi')->password()->revealable()->required(fn (string $operation): bool => $operation === 'create')->same('password')->dehydrated(fn (string $operation): bool => $operation === 'create'),
                Select::make('role_id')->label('Peran')->options(fn (): array => Role::query()->orderByRaw("CASE name WHEN 'superadmin' THEN 1 WHEN 'admin' THEN 2 WHEN 'bendahara' THEN 3 WHEN 'petugas' THEN 4 WHEN 'warga' THEN 5 ELSE 6 END")->orderBy('name')->pluck('name', 'id')->all())->required(fn (string $operation): bool => $operation === 'create')->dehydrated(fn (string $operation): bool => $operation === 'create'),
            ])->columns(2),
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
                TextColumn::make('roles.name')->label('Peran')->badge()->separator(','),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat')->authorize('manageView')->schema([
                    TextInput::make('name')->label('Nama'),
                    TextInput::make('status')->label('Status'),
                    TextInput::make('phone')->label('Nomor telepon'),
                    TextInput::make('email')->label('Email')->placeholder('Tidak tersedia'),
                    TextInput::make('role_label')->label('Peran')->placeholder('Belum diatur'),
                    TextInput::make('customer_number')->label('Nomor nasabah')->placeholder('Belum terbit'),
                    TextInput::make('staff_number')->label('Nomor petugas')->placeholder('Belum dibuat'),
                    TextInput::make('service_area_names')->label('Area pelayanan')->placeholder('Belum diatur'),
                ])->fillForm(function (User $record): array {
                    $record->loadMissing(['roles', 'customerProfile.rt.rw.dusun', 'staffProfile.serviceAreas.serviceArea']);

                    return [
                        'name' => $record->name,
                        'status' => $record->status->value,
                        'phone' => $record->phone,
                        'email' => $record->email,
                        'role_label' => $record->roles->pluck('name')->implode(', ') ?: null,
                        'customer_number' => $record->customerProfile?->customer_number,
                        'staff_number' => $record->staffProfile?->staff_number,
                        'service_area_names' => $record->customerProfile?->serviceRegion()
                            ?? $record->staffProfile?->serviceAreas
                                ->map(fn ($assignment): string => $assignment->serviceArea->name)
                                ->filter()
                                ->implode(', ') ?: null,
                    ];
                }),
                EditAction::make()->label('Ubah')->authorize('manageUpdate')->using(fn (User $record, array $data): User => app(ManageUsers::class)->update(self::actor(), $record, $data)),
                Action::make('assignRoles')
                    ->label('Atur peran')
                    ->icon(Heroicon::OutlinedUserGroup)
                    ->authorize(fn (User $record): bool => auth()->user() instanceof User && auth()->user()->can('manageUpdate', $record) && app(PermissionChecker::class)->allows(auth()->user(), 'role.manage'))
                    ->schema([
                        Select::make('role_ids')->label('Peran')->options(fn (): array => Role::query()->orderBy('name')->pluck('name', 'id')->all())->preload()->required(),
                        Textarea::make('reason')->label('Alasan perubahan peran')->required()->minLength(10)->maxLength(1000)->rows(3),
                    ])
                    ->fillForm(function (User $record): array {
                        $roleIds = $record->roles()->pluck('roles.id')->all();

                        return ['role_ids' => count($roleIds) === 1 ? $roleIds[0] : null];
                    })
                    ->requiresConfirmation()
                    ->modalHeading(fn (User $record): string => "Atur peran {$record->name}?")
                    ->modalDescription('Peran menentukan izin pengguna ini. Perubahan akan dicatat di riwayat aktivitas.')
                    ->modalSubmitActionLabel('Simpan peran')
                    ->action(fn (User $record, array $data): User => app(ManageRoles::class)->assignRoles(self::actor(), $record, [(int) $data['role_ids']], (string) $data['reason']))
                    ->successNotificationTitle('Peran pengguna diperbarui.'),
                Action::make('manageStaffProfile')
                    ->label('Atur area dan masa tugas')
                    ->icon(Heroicon::OutlinedMapPin)
                    ->authorize('manageUpdate')
                    ->visible(fn (User $record): bool => $record->roles->contains(static fn (Role $role): bool => in_array($role->name, ['petugas', 'bendahara'], true)))
                    ->schema([
                        Repeater::make('service_areas')->label('Area dan masa tugas')->schema([
                            Select::make('service_area_id')->label('Area pelayanan')->options(fn (): array => ServiceArea::query()->where('is_active', true)->orderBy('name')->pluck('name', 'id')->all())->preload()->required()->distinct(),
                            DatePicker::make('active_from')->label('Aktif mulai'),
                            DatePicker::make('active_to')->label('Aktif sampai')->afterOrEqual('active_from'),
                        ])->minItems(1)->defaultItems(1)->columns(3),
                    ])
                    ->fillForm(fn (User $record): array => ['service_areas' => $record->staffProfile?->serviceAreas->map(fn ($assignment): array => [
                        'service_area_id' => $assignment->service_area_id,
                        'active_from' => $assignment->active_from?->toDateString(),
                        'active_to' => $assignment->active_to?->toDateString(),
                    ])->all() ?? []])
                    ->action(fn (User $record, array $data): User => app(ManageStaffProfile::class)->save(self::actor(), $record, $data))
                    ->successNotificationTitle('Area dan masa tugas diperbarui.'),
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
            ->with(['customerProfile.rt.rw.dusun', 'roles', 'staffProfile.serviceArea', 'staffProfile.serviceAreas.serviceArea'])
            ->orderByRaw("CASE (SELECT roles.name FROM roles INNER JOIN role_user ON role_user.role_id = roles.id WHERE role_user.user_id = users.id LIMIT 1) WHEN 'superadmin' THEN 1 WHEN 'admin' THEN 2 WHEN 'bendahara' THEN 3 WHEN 'petugas' THEN 4 WHEN 'warga' THEN 5 ELSE 6 END")
            ->orderBy('name');
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
