<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Actions\ManageRoles;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Filament\Resources\Identity\Models\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Identity\Models\Users\UserResource;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class IdentityManagementResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_and_customer_queries_apply_own_area_and_all_scope_before_loading_records(): void
    {
        $own = User::factory()->create();
        $areaCustomer = User::factory()->create(['name' => 'Area Customer']);
        $outsideCustomer = User::factory()->create(['name' => 'Outside Customer']);
        [$area, $areaRt] = $this->areaWithRt('AREA');
        $outsideRt = $this->createRt('OUTSIDE');
        $this->runRegionMutation(fn () => $area->rts()->sync([$areaRt->id]));
        $staff = User::factory()->create();
        $staff->staffProfile()->create([
            'staff_number' => 'STF-'.str_pad((string) $staff->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        CustomerProfile::factory()->for($areaCustomer)->create(['rt_id' => $areaRt->id]);
        CustomerProfile::factory()->for($outsideCustomer)->create(['rt_id' => $outsideRt->id]);

        $this->grant($staff, 'area-user-manager', 'user.view', 'user.view.area', 'customer.view');
        $this->actingAs($staff->fresh());

        self::assertSame(
            [$areaCustomer->id, $staff->id],
            UserResource::getEloquentQuery()->orderBy('id')->pluck('id')->all(),
        );
        self::assertTrue(CustomerResource::getEloquentQuery()->whereKey($areaCustomer)->exists());
        self::assertFalse(CustomerResource::getEloquentQuery()->whereKey($outsideCustomer)->exists());
        self::assertFalse(UserResource::getEloquentQuery()->whereKey($outsideCustomer)->exists());

        $ownOnly = User::factory()->create();
        $this->grant($ownOnly, 'own-user-viewer', 'user.view', 'customer.view');
        $this->actingAs($ownOnly->fresh());
        self::assertSame([$ownOnly->id], UserResource::getEloquentQuery()->pluck('id')->all());

        $all = User::factory()->create();
        $this->grant($all, 'all-user-viewer', 'user.view', 'user.view.all', 'customer.view');
        $this->actingAs($all->fresh());
        self::assertTrue(UserResource::getEloquentQuery()->whereKey($outsideCustomer)->exists());
        self::assertTrue(CustomerResource::getEloquentQuery()->whereKey($outsideCustomer)->exists());
    }

    public function test_customer_resource_actions_use_domain_identity_action_without_exposing_qr_token(): void
    {
        $actor = User::factory()->create();
        $customer = User::factory()->create(['name' => 'Siti Aminah']);
        CustomerProfile::factory()->for($customer)->create(['customer_number' => null, 'qr_token_hash' => null]);
        $this->grant($actor, 'identity-operator', 'customer.view', 'customer.card.issue', 'customer.qr.rotate', 'user.view', 'user.view.all');
        $this->actingAs($actor->fresh());

        Livewire::test(ManageCustomers::class)
            ->assertActionVisible(TestAction::make('issueIdentity')->table($customer))
            ->callAction(TestAction::make('issueIdentity')->table($customer))
            ->assertDontSee($customer->fresh()->customerProfile->qr_token_encrypted);

        $issuedToken = $customer->fresh()->customerProfile->qr_token_encrypted;
        self::assertIsString($issuedToken);

        Livewire::test(ManageCustomers::class)
            ->assertActionVisible(TestAction::make('rotateQr')->table($customer))
            ->callAction(TestAction::make('rotateQr')->table($customer), data: ['reason' => 'QR lama diduga tersalin.'])
            ->assertDontSee($customer->fresh()->customerProfile->qr_token_encrypted);

        self::assertNotSame($issuedToken, $customer->fresh()->customerProfile->qr_token_encrypted);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'identity.customer.card_issued',
            'auditable_id' => $customer->id,
        ]);
        $rotatedAudit = AuditLog::query()
            ->where('actor_id', $actor->id)
            ->where('action', 'identity.customer.qr_rotated')
            ->where('auditable_id', $customer->id)
            ->sole();
        self::assertSame('QR lama diduga tersalin.', $rotatedAudit->new_values['reason']);
        $this->expectException(ValidationException::class);
        app(ManageCustomerIdentity::class)->scan($actor, $issuedToken);
    }

    public function test_role_assignment_records_actor_and_reason_and_system_roles_remain_protected(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::factory()->create(['name' => 'identity-operator']);
        $this->grant($actor, 'role-operator', 'role.manage', 'user.view', 'user.view.all', 'user.update');

        app(ManageRoles::class)->assignRoles($actor->fresh(), $target->fresh(), [$role->id], 'Penugasan petugas identity terverifikasi.');

        $this->assertDatabaseHas('role_user', [
            'role_id' => $role->id,
            'user_id' => $target->id,
            'assigned_by' => $actor->id,
            'reason' => 'Penugasan petugas identity terverifikasi.',
        ]);
        self::assertFalse(SystemRoles::contains('identity-operator'));
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'identity.user.roles_assigned',
            'auditable_id' => $target->id,
        ]);

        $systemRole = Role::factory()->create(['name' => 'admin']);
        $this->expectException(AuthorizationException::class);
        app(ManageRoles::class)->deleteRole($actor->fresh(), $systemRole);
    }

    public function test_lifecycle_mutations_are_scoped_and_audited(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->inactive()->create();
        $outside = User::factory()->inactive()->create();
        $this->grant($actor, 'lifecycle-manager', 'user.view', 'user.view.all', 'user.activate', 'user.update');

        app(ManageUsers::class)->activate($actor->fresh(), $target->fresh());
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'aktif']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'identity.user.activated', 'auditable_id' => $target->id]);

        app(ManageUsers::class)->deactivate($actor->fresh(), $target->fresh(), 'Akun tidak lagi bertugas di layanan.');
        $this->assertDatabaseHas('users', ['id' => $target->id, 'status' => 'nonaktif']);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'identity.user.deactivated', 'auditable_id' => $target->id]);

        $this->expectException(AuthorizationException::class);
        app(ManageUsers::class)->activate(User::factory()->create()->fresh(), $outside);
    }

    public function test_general_user_update_cannot_change_a_password(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['password' => Hash::make('kata-sandi-lama')]);
        $this->grant($actor, 'user-manager', 'user.view', 'user.view.all', 'user.update');

        app(ManageUsers::class)->update($actor->fresh(), $target->fresh(), [
            'name' => $target->name,
            'phone' => $target->phone,
            'email' => $target->email,
            'password' => 'kata-sandi-baru-yang-kuat',
        ]);

        self::assertTrue(Hash::check('kata-sandi-lama', $target->fresh()->password));
        self::assertFalse(Hash::check('kata-sandi-baru-yang-kuat', $target->fresh()->password));
    }

    public function test_customer_create_and_update_require_the_actors_service_area(): void
    {
        [$area, $allowedRt] = $this->areaWithRt('CUSTOMER-ALLOWED');
        $this->runRegionMutation(fn () => $area->rts()->sync([$allowedRt->id]));
        $outsideRt = $this->createRt('CUSTOMER-OUTSIDE');
        $actor = User::factory()->create();
        $actor->staffProfile()->create([
            'staff_number' => 'STF-'.str_pad((string) $actor->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        $this->grant($actor, 'customer-area-manager', 'user.view', 'user.view.area', 'customer.create-assisted', 'customer.update');

        $created = app(ManageUsers::class)->createCustomer($actor->fresh(), [
            'name' => 'Nasabah Area',
            'phone' => '628123456789',
            'email' => null,
            'rt_id' => $allowedRt->id,
            'address' => 'Alamat area sendiri',
        ]);
        self::assertSame($allowedRt->id, $created->customerProfile->rt_id);

        $this->expectException(AuthorizationException::class);
        app(ManageUsers::class)->createCustomer($actor->fresh(), [
            'name' => 'Nasabah Luar Area',
            'phone' => '628123456780',
            'email' => null,
            'rt_id' => $outsideRt->id,
            'address' => 'Alamat area lain',
        ]);
    }

    public function test_customer_update_rejects_moving_a_customer_outside_the_actors_service_area(): void
    {
        [$area, $allowedRt] = $this->areaWithRt('CUSTOMER-UPDATE-ALLOWED');
        $outsideRt = $this->createRt('CUSTOMER-UPDATE-OUTSIDE');
        $actor = User::factory()->create();
        $actor->staffProfile()->create([
            'staff_number' => 'STF-'.str_pad((string) $actor->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        $customer = User::factory()->create();
        $customer->customerProfile()->create(['rt_id' => $allowedRt->id, 'address' => 'Alamat area sendiri']);
        $this->grant($actor, 'customer-update-area', 'user.view', 'user.view.area', 'customer.update');

        $this->expectException(AuthorizationException::class);
        app(ManageUsers::class)->updateCustomer($actor->fresh(), $customer->fresh(), [
            'name' => $customer->name,
            'phone' => $customer->phone,
            'email' => $customer->email,
            'rt_id' => $outsideRt->id,
            'address' => 'Alamat area lain',
        ]);
    }

    public function test_deactivation_revokes_sessions_assignments_and_records_counts(): void
    {
        [$area, $rt] = $this->areaWithRt('DEACTIVATE');
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $customer = User::factory()->create();
        $role = Role::factory()->create(['name' => 'deactivation-assignment']);
        $target->roles()->attach($role, ['assigned_by' => $actor->id, 'reason' => 'Assignment aktif untuk uji.']);
        DB::table('sessions')->insert([
            'id' => 'deactivation-session',
            'user_id' => $target->id,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'PHPUnit',
            'payload' => 'payload',
            'last_activity' => now()->timestamp,
        ]);
        DB::table('pickup_requests')->insert([
            'request_number' => 'PUP-DEACTIVATE-001',
            'customer_id' => $customer->id,
            'rt_id' => $rt->id,
            'service_area_id' => $area->id,
            'address' => 'Alamat pickup',
            'selected_date' => today()->addDay()->toDateString(),
            'status' => PickupStatus::Scheduled->value,
            'assigned_staff_id' => $target->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->grant($actor, 'deactivation-manager', 'user.view', 'user.view.all', 'user.update');

        app(ManageUsers::class)->deactivate($actor->fresh(), $target->fresh(), 'Akun tidak lagi bertugas di layanan.');

        $this->assertDatabaseMissing('sessions', ['id' => 'deactivation-session']);
        $this->assertDatabaseMissing('role_user', ['user_id' => $target->id]);
        $this->assertDatabaseHas('pickup_requests', ['assigned_staff_id' => null, 'request_number' => 'PUP-DEACTIVATE-001']);
        $audit = AuditLog::query()->where('action', 'identity.user.deactivated')->where('auditable_id', $target->id)->sole();
        self::assertSame(1, $audit->new_values['sessions_revoked']);
        self::assertSame(1, $audit->new_values['role_assignments_revoked']);
        self::assertSame(1, $audit->new_values['operational_assignments_revoked']);
    }

    public function test_role_assignment_cannot_self_assign_or_cross_scope_and_lifecycle_actions_cannot_load_unscoped_records(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::factory()->create(['name' => 'limited-operator']);
        $this->grant($actor, 'limited-role-operator', 'role.manage', 'user.view', 'user.update');

        $this->expectException(AuthorizationException::class);
        app(ManageRoles::class)->assignRoles($actor->fresh(), $actor->fresh(), [$role->id], 'Penugasan role tidak boleh untuk diri sendiri.');

        $outside = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(ManageRoles::class)->assignRoles($actor->fresh(), $outside->fresh(), [$role->id], 'Penugasan role lintas scope harus ditolak.');

        $this->expectException(AuthorizationException::class);
        app(ManageUsers::class)->deactivate(User::factory()->create()->fresh(), $target->fresh(), 'Aktor tanpa permission tidak boleh mutasi.');
    }

    /** @return array{0: ServiceArea, 1: Rt} */
    private function areaWithRt(string $prefix): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-'.$prefix, 'name' => 'Dusun '.$prefix]);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-'.$prefix, 'name' => 'RW '.$prefix]);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-'.$prefix, 'name' => 'RT '.$prefix]);
        $area = ServiceArea::query()->create(['name' => 'Area '.$prefix, 'is_active' => true]);

        return [$area, $rt];
    }

    private function createRt(string $prefix): Rt
    {
        [, $rt] = $this->areaWithRt($prefix);

        return $rt;
    }

    private function runRegionMutation(\Closure $callback): void
    {
        RegionMutationGuard::run($callback);
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $role = Role::query()->create(['name' => $roleName, 'description' => 'Identity management test role']);
        foreach ($permissionNames as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => 'Identity management test permission']);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
