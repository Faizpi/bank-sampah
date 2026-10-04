<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Roles;

use App\Domain\Identity\Actions\ManageRoles as ManageRolesAction;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Filament\Resources\Identity\Models\Roles\Pages\ManageRoles;
use App\Models\User;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

final class RoleResource extends Resource
{
    protected static ?string $model = Role::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Role';

    protected static ?string $modelLabel = 'role';

    protected static ?string $pluralModelLabel = 'role';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Identitas role')
                ->schema([
                    TextInput::make('name')
                        ->label('Nama')
                        ->required()
                        ->minLength(2)
                        ->maxLength(50)
                        ->disabled(fn (?Role $record): bool => $record !== null && SystemRoles::contains($record->name))
                        ->helperText(fn (?Role $record): ?string => $record !== null && SystemRoles::contains($record->name)
                            ? 'Nama role sistem tidak dapat diubah.'
                            : null),
                    TextInput::make('description')->label('Deskripsi')->maxLength(255),
                ])
                ->columns(1),
            Section::make('Permission')
                ->schema([
                    CheckboxList::make('permissions')
                        ->label('Permission')
                        ->relationship('permissions', 'name')
                        ->columns(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(60)->searchable(),
                TextColumn::make('permissions_count')->label('Permission')->counts('permissions')->sortable(),
                TextColumn::make('users_count')->label('Pengguna')->counts('users')->sortable(),
            ])
            ->recordActions([
                EditAction::make()
                    ->label('Ubah')
                    ->using(fn (Role $record, array $data): Role => tap($record, fn () => app(ManageRolesAction::class)->updateRole(self::actor(), $record, $data['description'] ?? '', array_map('intval', $data['permissions'] ?? [])))),
                DeleteAction::make()
                    ->label('Hapus')
                    ->requiresConfirmation()
                    ->modalHeading('Hapus role')
                    ->modalDescription('Role yang dihapus tidak dapat dikembalikan.')
                    ->visible(fn (Role $record): bool => ! SystemRoles::contains($record->name))
                    ->authorize(fn (Role $record): bool => self::canDelete($record))
                    ->action(function (Role $record): void {
                        app(ManageRolesAction::class)->deleteRole(self::actor(), $record);
                    }),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageRoles::route('/')];
    }

    public static function canDelete(Model $record): bool
    {
        if ($record instanceof Role && SystemRoles::contains($record->name)) {
            return false;
        }

        return parent::canDelete($record);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
