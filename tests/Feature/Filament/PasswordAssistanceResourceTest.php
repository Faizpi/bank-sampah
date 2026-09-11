<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Actions\Auth\ChangePassword;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Filament\Resources\Identity\Models\PasswordAssistances\Pages\ManagePasswordAssistances;
use App\Filament\Resources\Identity\Models\PasswordAssistances\PasswordAssistanceResource;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class PasswordAssistanceResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_sees_active_and_pending_targets_and_can_assist_an_active_target(): void
    {
        $admin = User::factory()->create();
        $active = User::factory()->create();
        $pending = User::factory()->pendingVerification()->create();
        $this->grant($admin, 'admin', 'backoffice.access', 'user.view', 'user.view.all', 'user.reset-password', 'session.revoke');

        $this->actingAs($admin);

        self::assertTrue(PasswordAssistanceResource::canViewAny());
        Livewire::test(ManagePasswordAssistances::class)
            ->assertCanSeeTableRecords([$active, $pending])
            ->assertActionVisible(TestAction::make('changePassword')->table($active))
            ->callAction(TestAction::make('changePassword')->table($active), data: [
                'verification_method' => 'tatap_muka',
                'reason' => 'Pengguna hadir langsung dan identitasnya telah diperiksa.',
                'password' => 'kata-sandi-baru-yang-kuat',
                'password_confirmation' => 'kata-sandi-baru-yang-kuat',
            ])
            ->assertNotified();

        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $admin->id,
            'auditable_id' => $active->id,
            'action' => 'identity.password.changed.direct_admin',
        ]);
    }

    public function test_scoped_operator_lists_and_assists_only_visible_users(): void
    {
        [$operator, , $allowedRt] = $this->createStaffArea();
        $visible = User::factory()->create();
        $visible->customerProfile()->create($this->customerProfileAttributes($allowedRt));
        $outside = User::factory()->create();
        $outside->customerProfile()->create($this->customerProfileAttributes($this->createRt('RT-OUTSIDE')));
        $this->grant($operator, 'area-operator', 'backoffice.access', 'user.view', 'user.view.area', 'user.reset-password', 'session.revoke');

        $this->actingAs($operator->fresh());

        self::assertTrue(PasswordAssistanceResource::canViewAny());
        Livewire::test(ManagePasswordAssistances::class)
            ->assertCanSeeTableRecords([$operator, $visible])
            ->assertCanNotSeeTableRecords([$outside])
            ->assertActionVisible(TestAction::make('changePassword')->table($visible));

        // Scoped operators must never be allowed to reset/revoke an out-of-area user's password.
        self::assertFalse($operator->can('resetPassword', $outside));
        self::assertFalse($operator->can('revokeSession', $outside));
        self::assertTrue($operator->can('resetPassword', $visible));
        self::assertTrue($operator->can('revokeSession', $visible));
    }

    public function test_non_authorized_actor_cannot_list_or_execute_password_assistance(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $this->grant($actor, 'backoffice-user', 'backoffice.access');

        $this->actingAs($actor);

        self::assertFalse(PasswordAssistanceResource::canViewAny());
        $this->expectException(AuthorizationException::class);

        app(ChangePassword::class)->directAdmin(
            $actor,
            $target,
            'tatap_muka',
            'Pengguna hadir langsung dan identitasnya telah diperiksa.',
            'kata-sandi-baru-yang-kuat',
            'kata-sandi-baru-yang-kuat',
            (string) Str::uuid(),
        );
    }

    /** @return array{0: User, 1: ServiceArea, 2: Rt} */
    private function createStaffArea(): array
    {
        $operator = User::factory()->create();
        $area = ServiceArea::query()->create(['name' => 'Area '.$operator->id, 'is_active' => true]);
        $dusun = Dusun::query()->create(['code' => 'DS-'.$operator->id, 'name' => 'Dusun '.$operator->id, 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-'.$operator->id, 'name' => 'RW '.$operator->id, 'is_active' => true]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-'.$operator->id, 'name' => 'RT '.$operator->id, 'is_active' => true]);
        RegionMutationGuard::run(fn () => $area->rts()->sync([$rt->id]));
        StaffProfile::query()->create([
            'user_id' => $operator->id,
            'staff_number' => 'STF-'.str_pad((string) $operator->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);

        return [$operator, $area, $rt];
    }

    /** @return array{rt_id: int, address: string, joined_at: CarbonInterface} */
    private function customerProfileAttributes(Rt $rt): array
    {
        return ['rt_id' => $rt->id, 'address' => 'Alamat uji', 'joined_at' => now()];
    }

    private function createRt(string $code): Rt
    {
        $dusun = Dusun::query()->create(['code' => $code.'-D', 'name' => $code.' Dusun', 'is_active' => true]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code.'-W', 'name' => $code.' RW', 'is_active' => true]);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $code, 'is_active' => true]);
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

        $user->roles()->syncWithoutDetaching($role);
    }
}
