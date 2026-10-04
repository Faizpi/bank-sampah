<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Permissions;

use App\Domain\Identity\Models\Permission;
use App\Filament\Resources\Identity\Models\Permissions\Pages\ManagePermissions;
use BackedEnum;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

final class PermissionResource extends Resource
{
    protected static ?string $model = Permission::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedKey;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationLabel = 'Permission';

    protected static ?string $modelLabel = 'permission';

    protected static ?string $pluralModelLabel = 'permission';

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
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('description')->label('Deskripsi')->limit(80)->searchable(),
                TextColumn::make('roles_count')->label('Role')->counts('roles')->sortable(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePermissions::route('/')];
    }
}
