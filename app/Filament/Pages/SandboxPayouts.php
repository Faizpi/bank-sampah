<?php

declare(strict_types=1);

namespace App\Filament\Pages;

use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutChannels;
use App\Models\User;
use BackedEnum;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Schema;
use UnitEnum;

final class SandboxPayouts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static string|UnitEnum|null $navigationGroup = 'Pengawasan';

    protected static ?string $navigationLabel = 'Pencairan digital (simulasi)';

    protected static ?string $title = 'Pencairan digital (simulasi)';

    protected string $view = 'filament.backoffice.sandbox-payouts';

    public static function canAccess(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User && $actor->hasRole('admin') && $actor->status->value === 'aktif' && Schema::hasTable('sandbox_payouts');
    }

    public function table(Table $table): Table
    {
        return $table->query(SandboxPayout::query()->with('customer'))->defaultSort('id', 'desc')->columns([
            TextColumn::make('request_number')->label('Pengajuan')->searchable(),
            TextColumn::make('customer.name')->label('Warga')->searchable(),
            TextColumn::make('channel')->label('Tujuan')->formatStateUsing(fn (string $state): string => app(PayoutChannels::class)->all()[$state]['name']),
            TextColumn::make('amount')->label('Nominal (Rp)')->money('IDR'),
            TextColumn::make('status')->label('Status simulasi')->formatStateUsing(fn (SandboxPayout $record): string => $record->label()),
            TextColumn::make('created_at')->label('Diajukan')->dateTime('d/m/Y H:i'),
        ]);
    }
}
