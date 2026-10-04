<?php

declare(strict_types=1);

namespace App\Filament\Resources\Identity\Models\Customers\Pages;

use App\Domain\Identity\Actions\ManageUsers;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Models\User;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ManageRecords;

final class ManageCustomers extends ManageRecords
{
    protected static string $resource = CustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nasabah baru')->using(fn (array $data): User => app(ManageUsers::class)->createCustomer(auth()->user(), [
                'name' => $data['name'],
                'phone' => $data['phone'],
                'email' => $data['email'] ?? null,
                'rt_id' => (int) $data['rt_id'],
                'address' => $data['address'],
            ])),
        ];
    }
}
