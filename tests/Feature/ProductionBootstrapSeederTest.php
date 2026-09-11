<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\WasteMaster\Models\WastePrice;
use App\Models\User;
use Database\Seeders\ProductionBootstrapSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

final class ProductionBootstrapSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_bootstrap_creates_exact_production_master_data_without_demo_transactions(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);

        self::assertSame(1, Dusun::query()->where('code', 'BS-DESA')->count());
        self::assertSame(5, Rw::query()->whereIn('code', ['RW001', 'RW002', 'RW003', 'RW004', 'RW005'])->count());
        self::assertSame(19, Rt::query()->whereIn('code', array_map(static fn (int $rt): string => 'RT'.str_pad((string) $rt, 3, '0', STR_PAD_LEFT), range(1, 19)))->count());
        self::assertSame([
            'RT001' => ['Kp. Sukamaju — RT 001', 'RW 001'],
            'RT002' => ['Kp. Sukamaju — RT 002', 'RW 001'],
            'RT003' => ['Kp. Mekarsari — RT 003', 'RW 001'],
            'RT004' => ['Kp. Cibiru Kulon — RT 004', 'RW 002'],
            'RT005' => ['Kp. Cibiru Wetan — RT 005', 'RW 002'],
            'RT006' => ['Kp. Babakan — RT 006', 'RW 002'],
            'RT007' => ['Kp. Mekarsari Kidul — RT 007', 'RW 002'],
            'RT008' => ['Kp. Pasirhuni — RT 008', 'RW 003'],
            'RT009' => ['Kp. Kiarapayung — RT 009', 'RW 003'],
            'RT010' => ['Kp. Cisalak — RT 010', 'RW 003'],
            'RT011' => ['Kp. Cikadu — RT 011', 'RW 004'],
            'RT012' => ['Kp. Cikadu — RT 012', 'RW 004'],
            'RT013' => ['Kp. Binaan — RT 013', 'RW 004'],
            'RT014' => ['Kp. Pasir Kedung — RT 014', 'RW 004'],
            'RT015' => ['Kp. Patanjungan — RT 015', 'RW 005'],
            'RT016' => ['Kp. Paleuh — RT 016', 'RW 005'],
            'RT017' => ['Kp. Suka Sari — RT 017', 'RW 005'],
            'RT018' => ['Perumahan Griya Asri — RT 018', 'RW 005'],
            'RT019' => ['Kp. Mekarsari — RT 019', 'RW 001'],
        ], Rt::query()->with('rw')->whereIn('code', array_map(static fn (int $rt): string => 'RT'.str_pad((string) $rt, 3, '0', STR_PAD_LEFT), range(1, 19)))->orderBy('code')->get()->mapWithKeys(fn (Rt $rt): array => [$rt->code => [$rt->name, $rt->rw->name]])->all());
        self::assertSame(19, CustomerProfile::query()->whereHas('user.roles', fn ($query) => $query->where('name', 'warga'))->count());

        foreach (['petugas', 'bendahara', 'admin', 'superadmin'] as $username) {
            $user = User::query()->where('username', $username)->sole();
            self::assertTrue(Hash::check(ProductionBootstrapSeeder::INITIAL_PASSWORD, (string) $user->password));
            self::assertTrue($user->roles()->where('name', $username)->exists());
        }

        $area = ServiceArea::query()->where('name', 'Seluruh Desa Binaan')->sole();
        self::assertSame(19, $area->rts()->count());
        foreach (['petugas', 'bendahara'] as $username) {
            $user = User::query()->where('username', $username)->sole();
            self::assertSame(1, StaffServiceArea::query()->where('staff_profile_user_id', $user->id)->where('service_area_id', $area->id)->whereNull('active_to')->count());
        }

        self::assertSame([
            'Botol Plastik' => 3000,
            'Gelas Plastik' => 4000,
            'Kardus' => 1500,
            'Kertas' => 2000,
            'Logam' => 8000,
        ], WastePrice::query()->with('wasteType')->whereNull('effective_to')->whereHas('condition', fn ($query) => $query->where('code', 'BERSIH'))->get()->mapWithKeys(fn (WastePrice $price): array => [$price->wasteType->name => $price->price])->sortKeys()->all());

        self::assertSame(0, GroceryPackage::query()->count());
        self::assertSame(0, GroceryRedemption::query()->count());
        self::assertDatabaseCount('deposits', 0);
        self::assertDatabaseCount('pickup_requests', 0);
        self::assertDatabaseCount('withdrawal_requests', 0);
    }

    public function test_bootstrap_is_idempotent_and_does_not_reset_existing_passwords(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        $admin = User::query()->where('username', 'admin')->sole();
        $admin->forceFill(['password' => Hash::make('password-baru-admin')])->save();
        $counts = [User::query()->count(), Rt::query()->count(), WastePrice::query()->count()];

        $this->seed(ProductionBootstrapSeeder::class);

        self::assertSame($counts, [User::query()->count(), Rt::query()->count(), WastePrice::query()->count()]);
        self::assertTrue(Hash::check('password-baru-admin', (string) $admin->fresh()->password));
    }
}
