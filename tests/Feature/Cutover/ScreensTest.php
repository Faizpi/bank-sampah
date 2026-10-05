<?php

declare(strict_types=1);

use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the current screens for each of the four roles', function (string $role, string $path): void {
    $this->seed(DatabaseSeeder::class);
    $actor = User::where('username', $role)->firstOrFail();
    $this->actingAs($actor)->get($path)->assertOk();
})->with([
    ['warga', '/dashboard/warga'], ['warga', '/warga/kartu-nasabah'],
    ['warga', '/warga/riwayat-setoran'], ['warga', '/warga/riwayat-pencairan'], ['warga', '/warga/riwayat-sembako'],
    ['warga', '/warga/penjemputan/ajukan'], ['warga', '/warga/pencairan/ajukan'], ['warga', '/warga/sembako/ajukan'],
    ['petugas', '/dashboard/petugas'], ['petugas', '/petugas/pindai'], ['petugas', '/petugas/sembako'], ['petugas', '/petugas/layanan-keliling'],
    ['bendahara', '/dashboard/bendahara'], ['bendahara', '/bendahara/pencairan'], ['bendahara', '/bendahara/laporan'],
    ['admin', '/backoffice/work-queue-dashboard'], ['admin', '/backoffice/identity/models/users'],
    ['admin', '/backoffice/pickups/models/pickup-requests'], ['admin', '/backoffice/withdrawals/models/withdrawal-requests'], ['admin', '/backoffice/groceries/models/grocery-redemptions'],
]);

it('denies other roles routine actor routes', function (string $role, string $path): void {
    $this->seed(DatabaseSeeder::class);
    $this->actingAs(User::where('username', $role)->firstOrFail())->get($path)->assertForbidden();
})->with([
    ['admin', '/petugas/pindai'], ['admin', '/petugas/sembako'], ['admin', '/bendahara/pencairan'],
    ['petugas', '/bendahara/pencairan'], ['bendahara', '/petugas/sembako'],
    ['petugas', '/warga/pencairan/ajukan'], ['bendahara', '/warga/penjemputan/ajukan'], ['admin', '/warga/sembako/ajukan'],
]);
