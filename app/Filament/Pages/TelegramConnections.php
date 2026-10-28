<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Authorization\PermissionChecker;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use UnitEnum;

final class TelegramConnections extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = 'Keamanan & Akses';

    protected static ?string $navigationLabel = 'Tautan Telegram';

    protected static ?string $title = 'Tautan Telegram';

    protected string $view = 'filament.backoffice.telegram-connections';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return (bool) config('telegram.enabled') && $actor instanceof User && $actor->hasRole('admin') && app(PermissionChecker::class)->allows($actor, 'session.revoke');
    }

    public function table(Table $table): Table
    {
        return $table->query(TelegramLink::query()->with('user'))->columns([
            TextColumn::make('user.name')->label('Pengguna')->searchable(),
            TextColumn::make('telegram_id')->label('ID Telegram')->formatStateUsing(fn (string $state): string => '••••'.substr($state, -4)),
            TextColumn::make('created_at')->label('Tertaut sejak')->dateTime('d/m/Y H:i'),
        ])->recordActions([
            Action::make('revoke')->label('Cabut tautan')->color('danger')->schema([
                TextInput::make('password')->label('Kata sandi Admin saat ini')->password()->required()->autocomplete('current-password'),
                Textarea::make('reason')->label('Alasan pencabutan')->required()->minLength(10)->maxLength(1000),
            ])->action(function (TelegramLink $record, array $data): void {
                abort_unless(self::canAccess(), 403);
                $actor = auth()->user();
                abort_unless($actor instanceof User && $record->user instanceof User, 403);
                app(TelegramLinkService::class)->revoke($actor, $record->user, (string) $data['password'], (string) $data['reason']);
            }),
        ]);
    }
}
