<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\ResolveCitizenVerification;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Filament\Auth\Responses\BackofficeLoginResponse;
use App\Livewire\Citizen\CustomerCard;
use App\Livewire\Officer\Dashboard as OfficerDashboard;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class MultiRoleRedirectAndQrActivationTest extends TestCase
{
    use RefreshDatabase;

    public function test_backoffice_login_response_ignores_external_frontoffice_intended_url(): void
    {
        $superadmin = User::factory()->create([
            'username' => 'superadmin_test',
            'password' => Hash::make('password123'),
            'status' => UserStatus::Active,
        ]);
        $role = Role::query()->create(['name' => 'superadmin', 'description' => 'Superadmin']);
        $permission = Permission::query()->create(['name' => 'backoffice.access', 'description' => 'Backoffice']);
        $role->permissions()->attach($permission);
        $superadmin->roles()->attach($role);

        // Put officer dashboard as intended URL in session
        session()->put('url.intended', url('/dashboard/petugas'));

        $this->actingAs($superadmin);

        $loginResponse = new BackofficeLoginResponse;
        $response = $loginResponse->toResponse(request());

        // Must redirect to backoffice, NOT /dashboard/petugas
        self::assertStringContainsString('/backoffice', $response->getTargetUrl());
        self::assertStringNotContainsString('/dashboard/petugas', $response->getTargetUrl());
        self::assertNull(session('url.intended'));
    }

    public function test_officer_dashboard_redirects_superadmin_to_backoffice(): void
    {
        $superadmin = User::factory()->create([
            'name' => 'Agus Superadmin',
            'username' => 'superadmin_user',
            'status' => UserStatus::Active,
        ]);
        $role = Role::query()->create(['name' => 'superadmin', 'description' => 'Superadmin']);
        $perm1 = Permission::query()->create(['name' => 'user.view', 'description' => 'User view']);
        $perm2 = Permission::query()->create(['name' => 'backoffice.access', 'description' => 'Backoffice access']);
        $role->permissions()->attach([$perm1->id, $perm2->id]);
        $superadmin->roles()->attach($role);

        $this->actingAs($superadmin);

        Livewire::test(OfficerDashboard::class)
            ->assertRedirect(route('filament.backoffice.home'));
    }

    public function test_legacy_sh_customer_number_is_normalized_on_customer_profile(): void
    {
        $user = User::factory()->create(['status' => UserStatus::Active]);
        $dusun = Dusun::query()->create(['code' => 'DSN-1', 'name' => 'Dusun 1']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-1', 'name' => 'RW 1']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-1', 'name' => 'RT 1']);

        $profile = CustomerProfile::query()->create([
            'user_id' => $user->id,
            'customer_number' => 'SH-00019',
            'rt_id' => $rt->id,
            'address' => 'Jl. Melati',
        ]);

        self::assertSame('CST-00000019', $profile->customerNumber()->value());
    }

    public function test_citizen_verification_automatically_issues_customer_number_and_qr(): void
    {
        $admin = User::factory()->create(['status' => UserStatus::Active]);
        $wargaRole = Role::query()->create(['name' => 'warga', 'description' => 'Warga']);
        $verifyPerm = Permission::query()->create(['name' => 'user.verify', 'description' => 'Verify']);
        $viewPerm = Permission::query()->create(['name' => 'user.view', 'description' => 'View']);
        $viewAllPerm = Permission::query()->create(['name' => 'user.view.all', 'description' => 'View all']);
        $adminRole = Role::query()->create(['name' => 'admin', 'description' => 'Admin']);
        $adminRole->permissions()->attach([$verifyPerm, $viewPerm, $viewAllPerm]);
        $admin->roles()->attach($adminRole);

        $dusun = Dusun::query()->create(['code' => 'DSN-2', 'name' => 'Dusun 2']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-2', 'name' => 'RW 2']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-2', 'name' => 'RT 2']);

        $pendingUser = User::factory()->create(['status' => UserStatus::PendingVerification]);
        $pendingUser->roles()->attach($wargaRole);
        $profile = CustomerProfile::query()->create([
            'user_id' => $pendingUser->id,
            'customer_number' => null,
            'rt_id' => $rt->id,
            'address' => 'Jl. Desa',
        ]);

        $resolver = app(ResolveCitizenVerification::class);
        $resolved = $resolver->verify($admin, $pendingUser, (string) Str::uuid());

        self::assertSame(UserStatus::Active, $resolved->status);

        $profile->refresh();
        self::assertNotNull($profile->customer_number);
        self::assertMatchesRegularExpression('/^CST-[0-9]{8}$/', $profile->customer_number);
        self::assertNotNull($profile->qr_token_hash);
        self::assertNotNull($profile->qr_token_encrypted);
    }

    public function test_customer_card_auto_provisions_qr_for_active_citizen_with_missing_qr(): void
    {
        $citizen = User::factory()->create(['status' => UserStatus::Active]);
        $wargaRole = Role::query()->create(['name' => 'warga', 'description' => 'Warga']);
        $viewPerm = Permission::query()->create(['name' => 'customer.view', 'description' => 'View']);
        $wargaRole->permissions()->attach($viewPerm);
        $citizen->roles()->attach($wargaRole);

        $dusun = Dusun::query()->create(['code' => 'DSN-3', 'name' => 'Dusun 3']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-3', 'name' => 'RW 3']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-3', 'name' => 'RT 3']);

        CustomerProfile::query()->create([
            'user_id' => $citizen->id,
            'customer_number' => null,
            'rt_id' => $rt->id,
            'address' => 'Kp. Cikadu',
        ]);

        $this->actingAs($citizen);

        Livewire::test(CustomerCard::class)
            ->assertOk()
            ->assertSet('available', true)
            ->assertSeeHtml('data-customer-card-qr');
    }
}
