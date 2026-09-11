<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Filament\Auth\BackofficeLogin;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;
use Tests\TestCase;

final class BackofficeLoginFormTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'rahasia-deploy-2026';

    public function test_backoffice_login_page_renders_the_custom_branded_view(): void
    {
        $this->get('/backoffice/login')
            ->assertOk()
            ->assertSee('Panel pengelolaan bank sampah')
            ->assertSee('Masuk dengan akun Anda')
            ->assertSee('Kembali ke halaman utama')
            ->assertSee(route('home'), false);
    }

    public function test_the_panel_uses_the_custom_backoffice_login_class(): void
    {
        self::assertSame(
            BackofficeLogin::class,
            Filament::getPanel('backoffice')->getLoginRouteAction(),
        );
    }

    public function test_demo_fill_populates_the_username_and_password_fields_for_admin_and_superadmin(): void
    {
        Livewire::test(BackofficeLogin::class)
            ->call('fillDemo', 'admin')
            ->assertFormSet([
                'username' => 'admin',
                'password' => 'Banten123',
            ]);

        Livewire::test(BackofficeLogin::class)
            ->call('fillDemo', 'superadmin')
            ->assertFormSet([
                'username' => 'superadmin',
                'password' => 'Banten123',
            ]);
    }

    public function test_demo_fill_is_a_noop_for_unknown_roles(): void
    {
        Livewire::test(BackofficeLogin::class)
            ->call('fillDemo', 'misterius')
            ->assertFormSet([]);
    }

    public function test_staff_logs_in_via_the_backoffice_form_with_normalized_username_and_password(): void
    {
        $staff = User::factory()->create(['username' => 'admin.desa', 'password' => Hash::make(self::PASSWORD)]);
        $this->grant($staff, 'admin', 'backoffice.access');

        Livewire::test(BackofficeLogin::class)
            ->fillForm([
                'username' => '  ADMIN.DESA  ',
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        self::assertSame($staff->id, Filament::auth()->id());
    }

    public function test_backoffice_login_form_rejects_an_incorrect_password(): void
    {
        $staff = User::factory()->create(['username' => 'admin.desa', 'password' => Hash::make(self::PASSWORD)]);
        $this->grant($staff, 'admin', 'backoffice.access');

        Livewire::test(BackofficeLogin::class)
            ->fillForm([
                'username' => $staff->username,
                'password' => 'salah-sekali',
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        self::assertNull(Filament::auth()->id());
    }

    public function test_email_cannot_replace_username_as_the_backoffice_credential(): void
    {
        $user = User::factory()->withEmail()->create(['username' => 'admin.desa', 'password' => Hash::make(self::PASSWORD)]);
        $this->grant($user, 'admin', 'backoffice.access');

        Livewire::test(BackofficeLogin::class)
            ->fillForm([
                'username' => (string) $user->email,
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        self::assertNull(Filament::auth()->id());
    }

    public function test_backoffice_login_form_denies_a_user_without_backoffice_access_permission(): void
    {
        $withoutAccess = User::factory()->create(['username' => 'tanpa.akses', 'password' => Hash::make(self::PASSWORD)]);

        Livewire::test(BackofficeLogin::class)
            ->fillForm([
                'username' => $withoutAccess->username,
                'password' => self::PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasFormErrors(['username']);

        self::assertNull(Filament::auth()->id());
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => "Test role {$roleName}"],
        );

        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(
                ['name' => $permissionName],
                ['description' => "Test permission {$permissionName}"],
            );
            $role->permissions()->syncWithoutDetaching($permission);
        }

        $user->roles()->attach($role);
    }
}
