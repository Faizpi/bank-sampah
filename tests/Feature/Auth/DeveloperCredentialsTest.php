<?php

declare(strict_types=1);

namespace Tests\Feature\Auth;

use App\Actions\Auth\LogoutUser;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Livewire\Auth\LoginForm;
use App\Models\User;
use Database\Seeders\DeveloperUsersSeeder;
use Filament\Auth\Pages\Login as FilamentLogin;
use Filament\Facades\Filament;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class DeveloperCredentialsTest extends TestCase
{
    use RefreshDatabase;

    public function test_dev_seeder_creates_one_active_account_with_email_and_phone_for_every_role(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        foreach (DeveloperUsersSeeder::telephones() as $role => $phone) {
            $user = $this->devUser($role);

            self::assertNotNull($user, "Dev account missing for role [{$role}].");
            self::assertSame(UserStatus::Active, $user->status);
            self::assertNotNull($user->email);
            self::assertNotNull($user->phone);
            self::assertTrue($user->roles->pluck('name')->contains($role));
        }
    }

    public function test_warga_petugas_bendahara_and_superadmin_log_in_through_the_public_phone_gate(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        foreach (['warga', 'petugas', 'bendahara', 'superadmin'] as $role) {
            $this->loginViaPublicPhone($this->devUser($role));
            app(LogoutUser::class)->handle(request());
        }
    }

    public function test_seeded_bendahara_can_render_the_empty_report_route(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $bendahara = $this->devUser('bendahara');

        $this->actingAs($bendahara)
            ->get(route('treasurer.reports'))
            ->assertOk()
            ->assertSee('Laporan Setoran')
            ->assertSee('Filter periode')
            ->assertSee('0.000');
    }

    public function test_admin_logs_in_through_the_filament_backoffice_form(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $admin = $this->devUser('admin');

        Livewire::test(FilamentLogin::class)
            ->fillForm([
                'email' => $admin->email,
                'password' => DeveloperUsersSeeder::DEV_PASSWORD,
            ])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        self::assertSame($admin->id, Filament::auth()->id());
    }

    public function test_admin_and_superadmin_can_open_the_backoffice_panel(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        self::assertTrue($this->devUser('admin')->canAccessPanel($this->backofficePanel()));
        self::assertTrue($this->devUser('superadmin')->canAccessPanel($this->backofficePanel()));
    }

    public function test_warga_has_a_customer_profile_and_petugas_bendahara_have_staff_profiles(): void
    {
        $this->seed(DeveloperUsersSeeder::class);

        self::assertInstanceOf(CustomerProfile::class, $this->devUser('warga')->customerProfile);
        self::assertInstanceOf(StaffProfile::class, $this->devUser('petugas')->staffProfile);
        self::assertInstanceOf(StaffProfile::class, $this->devUser('bendahara')->staffProfile);
        self::assertNull($this->devUser('admin')->customerProfile);
        self::assertNull($this->devUser('admin')->staffProfile);
    }

    public function test_dev_seeder_is_idempotent_and_does_not_duplicate_roles(): void
    {
        $this->seed(DeveloperUsersSeeder::class);
        $this->seed(DeveloperUsersSeeder::class);

        foreach (array_keys(DeveloperUsersSeeder::telephones()) as $role) {
            self::assertSame(1, Role::query()->where('name', $role)->count());
            self::assertSame(1, $this->devUser($role)->roles()->where('name', $role)->count());
        }
    }

    private function loginViaPublicPhone(mixed $user): void
    {
        Livewire::test(LoginForm::class)
            ->set('phone', $user->phone)
            ->set('password', DeveloperUsersSeeder::DEV_PASSWORD)
            ->call('login')
            ->assertHasNoErrors();

        self::assertAuthenticatedAs($user);
    }

    private function devUser(string $role): User
    {
        return User::query()->where('phone', DeveloperUsersSeeder::telephone($role))->firstOrFail();
    }

    private function backofficePanel(): Panel
    {
        return Filament::getPanel('backoffice');
    }
}
