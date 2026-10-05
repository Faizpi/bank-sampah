<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Customers;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Filament\Resources\Identity\Models\Customers\Pages\ManageCustomers;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
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

final class CustomerResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedIdentification;

    protected static string|UnitEnum|null $navigationGroup = 'Data Master';

    protected static ?string $navigationParentItem = 'Direktori';

    protected static ?int $navigationSort = 10;

    protected static ?string $navigationLabel = 'Nasabah';

    protected static ?string $modelLabel = 'nasabah';

    protected static ?string $pluralModelLabel = 'nasabah';

    protected static ?string $recordTitleAttribute = 'name';

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(PermissionChecker::class)->allows($actor, 'customer.view')
            && (app(PermissionChecker::class)->allows($actor, 'customer.update')
                || app(PermissionChecker::class)->allows($actor, 'customer.create-assisted')
                || app(PermissionChecker::class)->allows($actor, 'customer.card.issue')
                || app(PermissionChecker::class)->allows($actor, 'customer.qr.rotate'));
    }

    public static function canCreate(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && app(PermissionChecker::class)->allows($actor, 'customer.create-assisted');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Profil nasabah')->schema([
                TextInput::make('name')->label('Nama')->required()->minLength(2)->maxLength(120),
                TextInput::make('phone')->label('Nomor telepon')->required()->regex('/^62[0-9]{8,16}$/')->maxLength(20),
                TextInput::make('email')->label('Email')->email()->nullable(),
                Select::make('rt_id')->label('RT')->options(fn (): array => Rt::query()->where('is_active', true)->whereHas('rw', fn (Builder $rw): Builder => $rw->where('is_active', true)->whereHas('dusun', fn (Builder $dusun): Builder => $dusun->where('is_active', true)))->orderBy('name')->pluck('name', 'id')->all())->searchable()->preload()->required(),
                Textarea::make('address')->label('Alamat')->required()->minLength(5)->maxLength(500)->rows(3),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('name')
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('customerProfile.customer_number')->label('Nomor nasabah')->placeholder('Belum terbit')->searchable(),
                TextColumn::make('customerProfile.rt.name')->label('RT')->searchable(),
                TextColumn::make('status')->label('Status')->badge(),
            ])
            ->recordActions([
                ViewAction::make()->label('Lihat')->authorize('manageView')->schema([
                    TextInput::make('name')->label('Nama'),
                    TextInput::make('status')->label('Status'),
                    TextInput::make('customer_number')->label('Nomor nasabah')->placeholder('Belum terbit'),
                    TextInput::make('service_region')->label('Wilayah layanan')->placeholder('Belum diatur'),
                ])->fillForm(function (User $record): array {
                    $record->loadMissing('customerProfile.rt.rw.dusun');

                    return [
                        'name' => $record->name,
                        'status' => $record->status->value,
                        'customer_number' => $record->customerProfile?->customer_number,
                        'service_region' => $record->customerProfile?->serviceRegion(),
                    ];
                }),
                EditAction::make()->label('Ubah')->authorize('updateCustomer')->using(fn (User $record, array $data): User => app(ManageUsers::class)->updateCustomer(self::actor(), $record, [
                    'name' => $data['name'],
                    'phone' => $data['phone'],
                    'email' => $data['email'] ?? null,
                    'rt_id' => (int) $data['rt_id'],
                    'address' => $data['address'],
                ])),
                Action::make('issueIdentity')->label('Terbitkan kartu dan QR')->icon(Heroicon::OutlinedQrCode)->color('success')->authorize(fn (User $record): bool => auth()->user() instanceof User && auth()->user()->can('manageView', $record) && app(PermissionChecker::class)->allows(auth()->user(), 'customer.card.issue'))->visible(fn (User $record): bool => $record->customerProfile?->customer_number === null)->requiresConfirmation()->modalHeading('Terbitkan kartu dan QR?')->modalDescription('Nomor nasabah dan QR baru akan diterbitkan. QR tidak ditampilkan di panel ini.')->modalSubmitActionLabel('Terbitkan kartu dan QR')->action(function (User $record): void {
                    app(ManageCustomerIdentity::class)->issue(self::actor(), $record);
                })->successNotificationTitle('Kartu dan QR nasabah diterbitkan.'),
                Action::make('rotateQr')->label('Rotasi QR')->icon(Heroicon::OutlinedArrowPath)->color('warning')->authorize(fn (User $record): bool => auth()->user() instanceof User && auth()->user()->can('manageView', $record) && app(PermissionChecker::class)->allows(auth()->user(), 'customer.qr.rotate'))->visible(fn (User $record): bool => $record->customerProfile?->customer_number !== null)->requiresConfirmation()->modalHeading(fn (User $record): string => "Rotasi QR nasabah {$record->name}?")->modalDescription('QR lama langsung tidak aktif. Terbitkan QR baru hanya jika ada alasan pemeriksaan yang jelas.')->modalSubmitActionLabel('Rotasi QR')->schema([Textarea::make('reason')->label('Alasan rotasi')->required()->minLength(10)->maxLength(1000)->rows(3)])->action(function (User $record, array $data): void {
                    app(ManageCustomerIdentity::class)->rotateQr(self::actor(), $record, (string) $data['reason']);
                })->successNotificationTitle('QR nasabah dirotasi dan QR lama dinonaktifkan.'),
                Action::make('activate')->label('Aktifkan nasabah')->icon(Heroicon::OutlinedCheckCircle)->color('success')->authorize('activate')->visible(fn (User $record): bool => $record->status === UserStatus::Inactive)->requiresConfirmation()->modalHeading(fn (User $record): string => "Aktifkan nasabah {$record->name}?")->modalDescription('Nasabah dapat masuk kembali dan menggunakan layanan sesuai izin akunnya.')->modalSubmitActionLabel('Aktifkan nasabah')->action(fn (User $record): User => app(ManageUsers::class)->activate(self::actor(), $record))->successNotificationTitle('Nasabah diaktifkan.'),
                Action::make('deactivate')->label('Nonaktifkan nasabah')->icon(Heroicon::OutlinedNoSymbol)->color('danger')->authorize('deactivate')->visible(fn (User $record): bool => $record->status === UserStatus::Active)->requiresConfirmation()->modalHeading(fn (User $record): string => "Nonaktifkan nasabah {$record->name}?")->modalDescription('Nasabah tidak dapat masuk atau membuat transaksi baru. Riwayat dan saldo tetap tersimpan.')->modalSubmitActionLabel('Nonaktifkan nasabah')->schema([Textarea::make('reason')->label('Alasan')->required()->minLength(10)->maxLength(1000)->rows(3)])->action(fn (User $record, array $data): User => app(ManageUsers::class)->deactivate(self::actor(), $record, (string) $data['reason']))->successNotificationTitle('Nasabah dinonaktifkan.'),
            ]);
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ManageCustomers::route('/')];
    }

    /** @return Builder<User> */
    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return User::query()->whereKey([]);
        }

        return app(VisibleUsers::class)->queryFor($actor, ...UserStatus::cases())->whereHas('customerProfile')->with(['customerProfile.rt.rw.dusun']);
    }

    private static function actor(): User
    {
        /** @var User $actor */
        $actor = auth()->user();

        return $actor;
    }
}
