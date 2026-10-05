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
use App\Domain\Identity\Actions\ManageStaffProfile;
use App\Domain\Identity\Actions\ManageUsers;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Support\SystemRoles;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Filament\Resources\Identity\Models\Customers\CustomerResource;
use App\Filament\Resources\Identity\Models\Customers\Pages\ManageCustomers;
use App\Filament\Resources\Identity\Models\Roles\Pages\ManageRoles as ManageRolesPage;
use App\Filament\Resources\Identity\Models\Users\Pages\ManageUsers as ManageUsersPage;
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

    public function test_internal_user_creation_activates_the_selected_role_account_without_exposing_password(): void
    {
        $actor = User::factory()->create();
        $role = Role::query()->create(['name' => 'petugas']);
        $this->grant($actor, 'internal-user-creator', 'user.create');

        $created = app(ManageUsers::class)->create($actor->fresh(), [
            'name' => 'Petugas Internal',
            'phone' => '6281234567890',
            'email' => 'petugas@example.test',
            'password' => 'rahasia-yang-kuat',
            'password_confirmation' => 'rahasia-yang-kuat',
            'role_id' => $role->id,
        ]);

        self::assertSame(UserStatus::Active, $created->status);
        self::assertTrue(Hash::check('rahasia-yang-kuat', (string) $created->password));
        self::assertSame([$role->id], $created->roles()->pluck('roles.id')->all());
        self::assertNull($created->customerProfile);
        $audit = AuditLog::query()->where('action', 'identity.user.created')->sole();
        self::assertSame(['status' => UserStatus::Active->value, 'role_id' => $role->id], $audit->new_values);
        self::assertStringNotContainsString('rahasia-yang-kuat', json_encode($audit->new_values, JSON_THROW_ON_ERROR));
    }

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
            UserResource::getEloquentQuery()->pluck('id')->sort()->values()->all(),
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

    public function test_customer_query_orders_newest_records_first(): void
    {
        $actor = User::factory()->create();
        $older = User::factory()->create(['created_at' => now()->subMinute()]);
        $newer = User::factory()->create(['created_at' => now()]);
        CustomerProfile::factory()->for($older)->create();
        CustomerProfile::factory()->for($newer)->create();
        $this->grant($actor, 'customer-viewer', 'customer.view', 'user.view', 'user.view.all');
        $this->actingAs($actor->fresh());

        self::assertSame([$newer->id, $older->id], CustomerResource::getEloquentQuery()->latest('created_at')->pluck('id')->all());
    }

    public function test_pickup_review_uses_authorized_media_route_for_image_thumbnails(): void
    {
        $resource = file_get_contents(app_path('Filament/Resources/Pickups/Models/PickupRequests/PickupRequestResource.php'));

        self::assertIsString($resource);
        self::assertStringContainsString("route('pickup.media'", $resource);
        self::assertStringContainsString('str_starts_with($media->mime_type, \'image/\')', $resource);
        self::assertStringContainsString('<img class="h-24 w-24', $resource);
        self::assertStringNotContainsString('Storage::url(', $resource);
        self::assertStringNotContainsString('public_path(', $resource);
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

    public function test_user_resource_eager_loads_persisted_account_and_profile_context(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['phone' => '628123456789', 'email' => 'stored@example.test']);
        $target->customerProfile()->create(['customer_number' => 'NSB-00000001', 'rt_id' => $this->createRt('PERSISTED')->id, 'address' => 'Alamat tersimpan']);
        $target->roles()->attach(Role::query()->create(['name' => 'petugas']));
        [$area] = $this->areaWithRt('PERSISTED-STAFF');
        $target->staffProfile()->create(['staff_number' => 'STF-00000001', 'service_area_id' => $area->id]);
        $this->grant($actor, 'persisted-context-viewer', 'user.view', 'user.view.all');
        $this->actingAs($actor->fresh());

        $record = UserResource::getEloquentQuery()->whereKey($target)->sole();

        self::assertTrue($record->relationLoaded('customerProfile'));
        self::assertTrue($record->relationLoaded('roles'));
        self::assertTrue($record->relationLoaded('staffProfile'));
        self::assertTrue($record->staffProfile->relationLoaded('serviceArea'));
        self::assertSame('628123456789', $record->phone);
        self::assertSame('stored@example.test', $record->email);
        self::assertSame('petugas', $record->roles->sole()->name);
        self::assertSame('Area PERSISTED-STAFF', $record->staffProfile->serviceArea->name);
    }

    public function test_user_view_action_maps_persisted_context_into_flat_modal_state(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['phone' => '628123456789', 'email' => 'stored@example.test']);
        $target->customerProfile()->create(['customer_number' => 'NSB-00000001', 'rt_id' => $this->createRt('VIEW')->id, 'address' => 'Alamat tersimpan']);
        $target->roles()->attach(Role::factory()->create(['name' => 'petugas']));
        [$area] = $this->areaWithRt('VIEW-STAFF');
        $target->staffProfile()->create(['staff_number' => 'STF-00000001', 'service_area_id' => $area->id]);
        $this->grant($actor, 'view-action-reader', 'user.view', 'user.view.all');
        $this->actingAs($actor->fresh());

        Livewire::test(ManageUsersPage::class)
            ->mountTableAction('view', $target->fresh())
            ->assertTableActionDataSet([
                'phone' => '628123456789',
                'email' => 'stored@example.test',
                'role_label' => 'petugas',
                'customer_number' => 'NSB-00000001',
                'staff_number' => 'STF-00000001',
                'service_area_names' => 'RT VIEW, RW VIEW, Dusun VIEW',
            ]);
    }

    public function test_customer_and_resident_user_views_show_the_customer_profile_region_instead_of_free_form_address(): void
    {
        $actor = User::factory()->create();
        $resident = User::factory()->create(['name' => 'Warga Wilayah']);
        $rt = $this->createRt('REGION-VIEW');
        $resident->customerProfile()->create([
            'customer_number' => 'CST-90757568',
            'rt_id' => $rt->id,
            'address' => 'Alamat bebas yang tidak boleh ditampilkan sebagai wilayah',
        ]);
        $resident->roles()->attach(Role::factory()->create(['name' => 'warga']));
        $this->grant($actor, 'region-view-reader', 'user.view', 'user.view.all', 'customer.view', 'customer.update');
        $this->actingAs($actor->fresh());

        $expectedRegion = 'RT REGION-VIEW, RW REGION-VIEW, Dusun REGION-VIEW';

        Livewire::test(ManageCustomers::class)
            ->mountTableAction('view', $resident->fresh())
            ->assertTableActionDataSet([
                'customer_number' => 'CST-90757568',
                'service_region' => $expectedRegion,
            ]);

        Livewire::test(ManageUsersPage::class)
            ->mountTableAction('view', $resident->fresh())
            ->assertTableActionDataSet([
                'customer_number' => 'CST-90757568',
                'service_area_names' => $expectedRegion,
            ]);

        $resource = file_get_contents(app_path('Filament/Resources/Identity/Models/Customers/CustomerResource.php'));
        self::assertIsString($resource);
        self::assertStringContainsString("label('Wilayah layanan')", $resource);
        self::assertStringNotContainsString("customerProfile.address')->label('Alamat')", $resource);
    }

    public function test_role_edit_action_preloads_persisted_permissions(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['name' => 'editable-role']);
        $firstPermission = Permission::query()->firstOrCreate(['name' => 'role.permission.first'], ['description' => 'First persisted role permission']);
        $secondPermission = Permission::query()->firstOrCreate(['name' => 'role.permission.second'], ['description' => 'Second persisted role permission']);
        $role->permissions()->attach([$firstPermission->id, $secondPermission->id], ['granted_by' => $actor->id, 'reason' => 'Persisted role permissions for edit hydration.']);
        $this->grant($actor, 'role-editor', 'role.manage');
        $this->actingAs($actor->fresh());

        Livewire::test(ManageRolesPage::class)
            ->mountTableAction('edit', $role->fresh())
            ->assertTableActionDataSet([
                'permissions' => [$firstPermission->id, $secondPermission->id],
            ]);
    }

    public function test_user_create_action_dehydrates_confirmation_for_create_and_rejects_mismatch_at_field_level(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['name' => 'petugas']);
        $this->grant($actor, 'create-action-password-confirmation', 'user.create');
        $this->actingAs($actor->fresh());

        $created = app(ManageUsers::class)->create($actor->fresh(), [
            'name' => 'Konfirmasi Cocok',
            'phone' => '628123456789',
            'email' => 'cocok@example.test',
            'password' => 'Password12345',
            'password_confirmation' => 'Password12345',
            'role_id' => $role->id,
        ]);
        self::assertTrue(Hash::check('Password12345', (string) $created->password));
        $audit = AuditLog::query()->where('action', 'identity.user.created')->sole();
        self::assertArrayNotHasKey('password_confirmation', $audit->new_values);
        self::assertStringNotContainsString('Password12345', json_encode($audit->new_values, JSON_THROW_ON_ERROR));

        try {
            app(ManageUsers::class)->create($actor->fresh(), [
                'name' => 'Konfirmasi Salah',
                'phone' => '628123456780',
                'email' => 'salah@example.test',
                'password' => 'Password12345',
                'password_confirmation' => 'Password54321',
                'role_id' => $role->id,
            ]);
            self::fail('Expected password confirmation validation failure.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('password', $exception->errors());
        }

        $this->assertDatabaseMissing('users', ['email' => 'salah@example.test']);
    }

    public function test_user_create_action_creates_user_and_reports_domain_validation_errors(): void
    {
        $actor = User::factory()->create();
        $role = Role::factory()->create(['name' => 'petugas']);
        $this->grant($actor, 'create-action-user', 'user.create');
        $this->actingAs($actor->fresh());

        $created = app(ManageUsers::class)->create($actor->fresh(), [
            'name' => 'Pengguna Baru',
            'phone' => '628123456789',
            'email' => 'baru@example.test',
            'password' => 'password-kuat-123',
            'password_confirmation' => 'password-kuat-123',
            'role_id' => $role->id,
        ]);

        self::assertSame(UserStatus::Active, $created->status);
        $this->assertDatabaseHas('users', ['name' => 'Pengguna Baru', 'email' => 'baru@example.test', 'status' => UserStatus::Active->value]);

        $this->expectException(ValidationException::class);
        app(ManageUsers::class)->create($actor->fresh(), [
            'name' => 'Tanpa Peran',
            'phone' => '628123456780',
            'email' => 'invalid-role@example.test',
            'password' => 'password-kuat-123',
            'password_confirmation' => 'password-kuat-123',
            'role_id' => 999999,
        ]);
    }

    public function test_filament_role_assignment_accepts_a_single_scalar_role_id(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $role = Role::factory()->create(['name' => 'scalar-role']);
        $this->grant($actor, 'scalar-role-manager', 'role.manage', 'user.view', 'user.view.all', 'user.update');
        $this->actingAs($actor->fresh());

        Livewire::test(ManageUsersPage::class)
            ->callAction(TestAction::make('assignRoles')->table($target), data: [
                'role_ids' => $role->id,
                'reason' => 'Payload select tunggal dari tindakan Filament.',
            ]);

        self::assertSame([$role->id], $target->fresh()->roles()->pluck('roles.id')->all());
    }

    public function test_staff_profile_action_creates_updates_and_audits_operational_staff(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create(['phone' => '628123456789', 'email' => 'petugas@example.test']);
        $petugas = Role::query()->create(['name' => 'petugas']);
        $target->roles()->attach($petugas);
        [$area] = $this->areaWithRt('STAFF');
        $this->grant($actor, 'staff-profile-manager', 'user.view', 'user.view.all', 'user.update');
        $this->actingAs($actor->fresh());

        Livewire::test(ManageUsersPage::class)
            ->assertActionVisible(TestAction::make('manageStaffProfile')->table($target))
            ->callAction(TestAction::make('manageStaffProfile')->table($target), data: [
                'service_areas' => [[
                    'service_area_id' => $area->id,
                    'active_from' => '2026-01-01',
                    'active_to' => '2026-12-31',
                ]],
            ]);

        $profile = $target->fresh(['staffProfile.serviceArea'])->staffProfile;
        self::assertNotNull($profile);
        self::assertSame('STF-'.str_pad((string) $target->id, 8, '0', STR_PAD_LEFT), $profile->staff_number);
        self::assertSame($area->id, $profile->service_area_id);
        self::assertSame('Area STAFF', $profile->serviceArea->name);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'identity.staff_profile.created', 'auditable_id' => $target->id]);

        app(ManageStaffProfile::class)->save($actor->fresh(), $target->fresh(), [
            'service_area_id' => $area->id,
            'active_from' => '2026-02-01',
            'active_to' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', ['actor_id' => $actor->id, 'action' => 'identity.staff_profile.updated', 'auditable_id' => $target->id]);
    }

    public function test_staff_profile_rejects_invalid_area_dates_and_unscoped_subjects(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $target->roles()->attach(Role::query()->create(['name' => 'bendahara']));
        $inactiveArea = ServiceArea::query()->create(['name' => 'Area nonaktif', 'is_active' => false]);
        $this->grant($actor, 'staff-profile-limited', 'user.view', 'user.view.all', 'user.update');

        try {
            app(ManageStaffProfile::class)->save($actor->fresh(), $target->fresh(), ['service_area_id' => $inactiveArea->id, 'active_from' => null, 'active_to' => null]);
            self::fail('Expected inactive service area validation failure.');
        } catch (ValidationException $exception) {
            self::assertSame('Area pelayanan harus aktif.', $exception->errors()['service_area_id'][0]);
        }

        [$area] = $this->areaWithRt('STAFF-DATES');
        try {
            app(ManageStaffProfile::class)->save($actor->fresh(), $target->fresh(), ['service_area_id' => $area->id, 'active_from' => '2026-12-31', 'active_to' => '2026-01-01']);
            self::fail('Expected invalid date range validation failure.');
        } catch (ValidationException $exception) {
            self::assertSame('Tanggal akhir aktif tidak boleh lebih awal dari tanggal mulai aktif.', $exception->errors()['active_to'][0]);
        }

        $outsideActor = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(ManageStaffProfile::class)->save($outsideActor->fresh(), $target->fresh(), ['service_area_id' => $area->id, 'active_from' => null, 'active_to' => null]);
    }

    public function test_role_assignment_requires_exactly_one_role_without_altering_existing_assignments(): void
    {
        $actor = User::factory()->create();
        $target = User::factory()->create();
        $first = Role::factory()->create(['name' => 'first-role']);
        $second = Role::factory()->create(['name' => 'second-role']);
        $target->roles()->attach($first, ['assigned_by' => $actor->id, 'reason' => 'Penugasan awal yang tetap harus ada.']);
        $this->grant($actor, 'single-role-manager', 'role.manage', 'user.view', 'user.view.all', 'user.update');

        try {
            app(ManageRoles::class)->assignRoles($actor->fresh(), $target->fresh(), [$first->id, $second->id], 'Permintaan assignment dengan dua role.');
            self::fail('Expected role assignment validation failure.');
        } catch (ValidationException $exception) {
            self::assertSame('Setiap pengguna harus memiliki tepat satu role.', $exception->errors()['roles'][0]);
        }

        self::assertSame([$first->id], $target->fresh()->roles()->pluck('roles.id')->all());
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
