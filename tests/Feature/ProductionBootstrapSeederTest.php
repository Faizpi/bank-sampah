<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Groceries\Models\GroceryPackage;
use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
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

    private const INITIAL_PASSWORD = 'KataSandiBootstrap-Yang-Kuat-2026';

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('app.initial_admin_password', self::INITIAL_PASSWORD);
        config()->set('app.initial_admin_email', 'superadmin@example.test');
    }

    public function test_bootstrap_creates_exact_production_master_data_without_demo_transactions(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);

        self::assertSame(1, Dusun::query()->where('code', 'BS-DESA')->count());
        self::assertSame(5, Rw::query()->whereIn('code', ['RW001', 'RW002', 'RW003', 'RW004', 'RW005'])->count());
        self::assertSame(19, Rt::query()->whereIn('code', array_map(static fn (int $rt): string => 'RT'.str_pad((string) $rt, 3, '0', STR_PAD_LEFT), range(1, 19)))->count());
        self::assertSame([
            'RT001' => ['Kp. Sukamaju - RT 001', 'RW 001'],
            'RT002' => ['Kp. Sukamaju - RT 002', 'RW 001'],
            'RT003' => ['Kp. Mekarsari - RT 003', 'RW 001'],
            'RT004' => ['Kp. Cibiru Kulon - RT 004', 'RW 002'],
            'RT005' => ['Kp. Cibiru Wetan - RT 005', 'RW 002'],
            'RT006' => ['Kp. Babakan - RT 006', 'RW 002'],
            'RT007' => ['Kp. Mekarsari Kidul - RT 007', 'RW 002'],
            'RT008' => ['Kp. Pasirhuni - RT 008', 'RW 003'],
            'RT009' => ['Kp. Kiarapayung - RT 009', 'RW 003'],
            'RT010' => ['Kp. Cisalak - RT 010', 'RW 003'],
            'RT011' => ['Kp. Cikadu - RT 011', 'RW 004'],
            'RT012' => ['Kp. Cikadu - RT 012', 'RW 004'],
            'RT013' => ['Kp. Binaan - RT 013', 'RW 004'],
            'RT014' => ['Kp. Pasir Kedung - RT 014', 'RW 004'],
            'RT015' => ['Kp. Patanjungan - RT 015', 'RW 005'],
            'RT016' => ['Kp. Paleuh - RT 016', 'RW 005'],
            'RT017' => ['Kp. Suka Sari - RT 017', 'RW 005'],
            'RT018' => ['Perumahan Griya Asri - RT 018', 'RW 005'],
            'RT019' => ['Kp. Mekarsari - RT 019', 'RW 001'],
        ], Rt::query()->with('rw')->whereIn('code', array_map(static fn (int $rt): string => 'RT'.str_pad((string) $rt, 3, '0', STR_PAD_LEFT), range(1, 19)))->orderBy('code')->get()->mapWithKeys(fn (Rt $rt): array => [$rt->code => [$rt->name, $rt->rw->name]])->all());
        self::assertSame(19, CustomerProfile::query()->whereHas('user.roles', fn ($query) => $query->where('name', 'warga'))->count());

        foreach (['petugas', 'bendahara', 'admin', 'superadmin'] as $username) {
            $user = User::query()->where('username', $username)->sole();
            self::assertTrue(Hash::check(self::INITIAL_PASSWORD, (string) $user->password));
            self::assertTrue($user->roles()->where('name', $username)->exists());
        }

        self::assertSame('superadmin@example.test', User::query()->where('username', 'superadmin')->sole()->email);
        self::assertNull(User::query()->where('username', 'admin')->sole()->email);

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

    public function test_reseeding_leaves_existing_soft_deleted_superadmin_untouched_and_revokes_privileged_roles(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        $superadmin = User::query()->where('username', 'superadmin')->sole();

        $superadmin->forceFill([
            'name' => 'Pemilik Lama',
            'email' => 'pemilik.lama@example.test',
            'phone' => '628111111111',
            'status' => UserStatus::Inactive,
            'password' => Hash::make('kunci-lama-yang-kuat'),
            'deleted_at' => now(),
        ])->save();
        $observer = Role::query()->create(['name' => 'observer']);
        $superadmin->roles()->sync([$observer->id => ['assigned_by' => $superadmin->id, 'reason' => 'Peran dicabut']]);
        config()->set('app.initial_admin_password', 'KataSandi-Bootstrap-Baru-2026');

        $this->seed(ProductionBootstrapSeeder::class);

        $fresh = User::withTrashed()->where('username', 'superadmin')->sole();
        self::assertSame('Pemilik Lama', $fresh->name);
        self::assertSame('pemilik.lama@example.test', $fresh->email);
        self::assertSame('628111111111', $fresh->phone);
        self::assertSame(UserStatus::Inactive, $fresh->status);
        self::assertNotNull($fresh->deleted_at);
        self::assertTrue(Hash::check('kunci-lama-yang-kuat', (string) $fresh->password));
        self::assertSame([$observer->id], $fresh->roles()->pluck('roles.id')->all());
        self::assertFalse($fresh->roles()->where('name', 'superadmin')->exists());
    }

    public function test_reseeding_does_not_overwrite_existing_resident_identity_or_roles(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        $resident = User::query()->where('username', 'ahmadfauzi')->sole();
        $resident->forceFill([
            'name' => 'Nama Diperbarui',
            'email' => 'ahmad.baru@example.test',
            'phone' => '628222222222',
            'status' => UserStatus::Inactive,
        ])->save();
        $observer = Role::query()->create(['name' => 'observer']);
        $resident->roles()->sync([$observer->id => ['assigned_by' => $resident->id, 'reason' => 'Peran warga dicabut']]);

        $region = Rt::query()->where('code', 'RT001')->sole();
        CustomerProfile::query()->where('user_id', $resident->id)->update(['rt_id' => $region->id, 'address' => 'Alamat pilihan']);
        $profileBefore = CustomerProfile::query()->where('user_id', $resident->id)->sole()->getRawOriginal();
        $this->seed(ProductionBootstrapSeeder::class);

        self::assertSame($profileBefore, CustomerProfile::query()->where('user_id', $resident->id)->sole()->getRawOriginal());
        $fresh = User::withTrashed()->where('username', 'ahmadfauzi')->sole();
        self::assertSame('Nama Diperbarui', $fresh->name);
        self::assertSame('ahmad.baru@example.test', $fresh->email);
        self::assertSame('628222222222', $fresh->phone);
        self::assertSame(UserStatus::Inactive, $fresh->status);
        self::assertSame([$observer->id], $fresh->roles()->pluck('roles.id')->all());
        self::assertSame($region->id, CustomerProfile::query()->where('user_id', $fresh->id)->sole()->rt_id);
    }

    public function test_reseeding_never_recreates_a_soft_deleted_staff_account_or_its_profile(): void
    {
        $this->seed(ProductionBootstrapSeeder::class);
        $petugas = User::query()->where('username', 'petugas')->sole();
        $petugas->forceFill([
            'name' => 'Petugas Lama',
            'email' => 'petugas.lama@example.test',
            'status' => UserStatus::Inactive,
            'deleted_at' => now(),
        ])->save();
        $observer = Role::query()->create(['name' => 'observer']);
        $petugas->roles()->sync([$observer->id => ['assigned_by' => $petugas->id, 'reason' => 'Peran petugas dicabut']]);
        $profile = StaffProfile::query()->where('user_id', $petugas->id)->sole();
        $profile->forceFill(['active_from' => '2020-01-01', 'active_to' => '2021-01-01'])->save();
        StaffServiceArea::query()->where('staff_profile_user_id', $petugas->id)->delete();
        $profileBefore = $profile->getRawOriginal();

        $this->seed(ProductionBootstrapSeeder::class);

        $fresh = User::withTrashed()->where('username', 'petugas')->sole();
        self::assertSame('Petugas Lama', $fresh->name);
        self::assertSame('petugas.lama@example.test', $fresh->email);
        self::assertSame(UserStatus::Inactive, $fresh->status);
        self::assertNotNull($fresh->deleted_at);
        self::assertSame([$observer->id], $fresh->roles()->pluck('roles.id')->all());
        self::assertFalse($fresh->roles()->where('name', 'petugas')->exists());
        self::assertSame($profileBefore, StaffProfile::query()->where('user_id', $fresh->id)->sole()->getRawOriginal());
        self::assertSame(0, StaffServiceArea::query()->where('staff_profile_user_id', $fresh->id)->count());
    }

    public function test_bootstrap_fails_safely_without_an_explicit_initial_password(): void
    {
        config()->set('app.initial_admin_password', null);

        $this->expectException(\RuntimeException::class);
        $this->seed(ProductionBootstrapSeeder::class);
    }

    public function test_bootstrap_rejects_a_short_initial_password(): void
    {
        config()->set('app.initial_admin_password', 'pendek');

        $this->expectException(\RuntimeException::class);
        $this->seed(ProductionBootstrapSeeder::class);
    }

    public function test_bootstrap_rejects_an_invalid_initial_admin_email(): void
    {
        config()->set('app.initial_admin_email', 'bukan-email');

        $this->expectException(\RuntimeException::class);
        $this->seed(ProductionBootstrapSeeder::class);
    }
}
