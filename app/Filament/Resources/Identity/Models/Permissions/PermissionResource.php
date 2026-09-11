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

    protected static string|UnitEnum|null $navigationGroup = 'Keamanan & Akses';

    protected static ?int $navigationSort = 70;

    protected static ?string $navigationLabel = 'Izin';

    protected static ?string $modelLabel = 'izin';

    protected static ?string $pluralModelLabel = 'izin';

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
                TextColumn::make('roles_count')->label('Peran')->counts('roles')->sortable(),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManagePermissions::route('/')];
    }
}
