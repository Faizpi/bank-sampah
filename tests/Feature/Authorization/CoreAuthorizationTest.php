<?php

declare(strict_types=1);

namespace Tests\Feature\Authorization;

use App\Authorization\PermissionChecker;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Queries\VisibleUsers;
use App\Http\Middleware\RequirePermission;
use App\Models\User;
use App\Policies\UserPolicy;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class CoreAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_permission_checker_denies_by_default_and_grants_only_through_attached_permissions(): void
    {
        $user = User::factory()->create();
        $checker = new PermissionChecker;

        self::assertFalse($checker->allows($user, 'user.view'));

        $this->grant($user, 'petugas', 'user.view');

        self::assertTrue($checker->allows($user->fresh(), 'user.view'));
        self::assertFalse($checker->allows($user->fresh(), 'user.update'));
    }

    public function test_role_names_and_superadmin_do_not_bypass_granular_or_sensitive_permissions(): void
    {
        $superadmin = User::factory()->create();
        $superadmin->roles()->attach(Role::factory()->create(['name' => 'superadmin']));
        $checker = new PermissionChecker;

        foreach (['ledger.adjust', 'transaction.correct', 'withdrawal.approve', 'withdrawal.pay', 'grocery.approve', 'grocery.handover'] as $permission) {
            self::assertFalse($checker->allows($superadmin, $permission));
        }
    }

    public function test_inactive_and_soft_deleted_users_are_denied_even_when_their_role_has_permission(): void
    {
        $inactive = User::factory()->inactive()->create();
        $deleted = User::factory()->create();
        $this->grant($inactive, 'admin', 'user.view');
        $this->grant($deleted, 'admin', 'user.view');
        $deleted->delete();
        $checker = new PermissionChecker;

        self::assertFalse($checker->allows($inactive, 'user.view'));
        self::assertFalse($checker->allows($deleted, 'user.view'));
    }

    public function test_direct_url_middleware_denies_guests_and_users_without_the_exact_permission(): void
    {
        $middleware = new RequirePermission(new PermissionChecker);
        $request = Request::create('/internal/users');

        $this->expectException(AuthorizationException::class);
        $middleware->handle($request, static fn (): Response => new Response('secret'), 'user.view');
    }

    public function test_direct_url_middleware_allows_the_exact_permission_only(): void
    {
        $user = User::factory()->create();
        $this->grant($user, 'petugas', 'user.view');
        $request = Request::create('/internal/users');
        $request->setUserResolver(static fn (): User => $user->fresh());
        $middleware = new RequirePermission(new PermissionChecker);

        $response = $middleware->handle($request, static fn (): Response => new Response('ok'), 'user.view');

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('ok', $response->getContent());
    }

    public function test_user_policy_uses_own_profile_permissions_and_separate_other_user_permissions(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $this->grant($actor, 'warga', 'profile.view', 'profile.update');
        $policy = new UserPolicy(new PermissionChecker, new VisibleUsers(new PermissionChecker));

        self::assertTrue($policy->view($actor->fresh(), $actor));
        self::assertTrue($policy->update($actor->fresh(), $actor));
        self::assertFalse($policy->view($actor->fresh(), $other));
        self::assertFalse($policy->update($actor->fresh(), $other));
        self::assertFalse($policy->activate($actor->fresh(), $other));
        self::assertFalse($policy->verify($actor->fresh(), $other));
        self::assertFalse($policy->reject($actor->fresh(), $other));
        self::assertFalse($policy->resetPassword($actor->fresh(), $other));
        self::assertFalse($policy->revokeSession($actor->fresh(), $other));

        self::assertTrue(Gate::forUser($actor->fresh())->allows('view', $actor));
        self::assertFalse(Gate::forUser($actor->fresh())->allows('view', $other));
    }

    public function test_visible_users_http_boundary_denies_guests_and_users_without_the_exact_permission(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $url = route('test.visible-users.show', $subject);

        $this->get($url)->assertRedirect(route('login'));
        $this->actingAs($actor)->get($url)->assertForbidden();
    }

    public function test_visible_users_http_boundary_allows_active_users_with_the_exact_permission(): void
    {
        $actor = User::factory()->create();
        $subject = User::factory()->create();
        $this->grant($actor, 'custom', 'user.view', 'user.view.all');

        $this->actingAs($actor->fresh())
            ->get(route('test.visible-users.show', $subject))
            ->assertOk()
            ->assertExactJson(['id' => $subject->id]);
    }

    public function test_visible_users_http_boundary_denies_inactive_and_soft_deleted_users_with_permission(): void
    {
        $inactive = User::factory()->inactive()->create();
        $deleted = User::factory()->create();
        $subject = User::factory()->create();
        $this->grant($inactive, 'petugas', 'user.view');
        $this->grant($deleted, 'petugas', 'user.view');
        $deleted->delete();
        $url = route('test.visible-users.show', $subject);

        $this->actingAs($inactive)->get($url)->assertForbidden();
        $this->actingAs($deleted)->get($url)->assertForbidden();
    }

    public function test_visible_users_http_boundary_scopes_before_id_retrieval(): void
    {
        $actor = User::factory()->create();
        $inactiveSubject = User::factory()->inactive()->create();
        $deletedSubject = User::factory()->create();
        $this->grant($actor, 'petugas', 'user.view');
        $deletedSubject->delete();

        $this->actingAs($actor->fresh())
            ->get(route('test.visible-users.show', $inactiveSubject))
            ->assertNotFound();
        $this->actingAs($actor->fresh())
            ->get(route('test.visible-users.show', $deletedSubject->id))
            ->assertNotFound();
    }

    public function test_visible_users_defaults_to_own_even_when_user_view_has_no_scope_grant(): void
    {
        $actor = User::factory()->create();
        $other = User::factory()->create();
        $scope = new VisibleUsers(new PermissionChecker);

        $this->grant($actor, 'custom', 'user.view');

        self::assertSame([$actor->id], $scope->queryFor($actor->fresh())->pluck('id')->all());
        self::assertNull($scope->queryFor($actor)->find($other->id));
    }

    public function test_area_scope_sees_self_and_active_customers_in_effective_active_service_area_rts(): void
    {
        [$actor, $area, $allowedRt] = $this->createStaffArea(today()->subDay()->toDateString(), null, true, true);
        $allowedCustomer = User::factory()->create();
        $allowedCustomer->customerProfile()->create($this->customerProfileAttributes($allowedRt));
        $outsideCustomer = User::factory()->create();
        $outsideCustomer->customerProfile()->create($this->customerProfileAttributes($this->createRt('RT-OUTSIDE')));
        $staffTarget = User::factory()->create();
        $inactiveCustomer = User::factory()->inactive()->create();
        $inactiveCustomer->customerProfile()->create($this->customerProfileAttributes($allowedRt));
        $scope = new VisibleUsers(new PermissionChecker);
        $this->grant($actor, 'custom', 'user.view', 'user.view.area');

        self::assertSame(
            [$actor->id, $allowedCustomer->id],
            $scope->queryFor($actor->fresh())->orderBy('id')->pluck('id')->all(),
        );
        self::assertTrue((new UserPolicy(new PermissionChecker, $scope))->view($actor->fresh(), $allowedCustomer));
        self::assertFalse((new UserPolicy(new PermissionChecker, $scope))->view($actor->fresh(), $outsideCustomer));
        self::assertFalse((new UserPolicy(new PermissionChecker, $scope))->view($actor->fresh(), $staffTarget));
        self::assertFalse((new UserPolicy(new PermissionChecker, $scope))->view($actor->fresh(), $inactiveCustomer));
    }

    public function test_scoped_user_policy_denies_password_assistance_outside_the_visible_users_scope(): void
    {
        [$actor, , $allowedRt] = $this->createStaffArea(today()->subDay()->toDateString(), null, true, true);
        $visible = User::factory()->create();
        $visible->customerProfile()->create($this->customerProfileAttributes($allowedRt));
        $outside = User::factory()->create();
        $outside->customerProfile()->create($this->customerProfileAttributes($this->createRt('RT-POLICY-OUTSIDE')));
        $this->grant($actor, 'password-assistance-area', 'user.view', 'user.view.area', 'user.reset-password', 'session.revoke');
        $policy = new UserPolicy(new PermissionChecker, new VisibleUsers(new PermissionChecker));

        self::assertTrue($policy->resetPassword($actor->fresh(), $visible));
        self::assertTrue($policy->revokeSession($actor->fresh(), $visible));
        self::assertFalse($policy->resetPassword($actor->fresh(), $outside));
        self::assertFalse($policy->revokeSession($actor->fresh(), $outside));
    }

    public function test_area_scope_requires_effective_staff_profile_and_active_area_and_rt(): void
    {
        $actor = User::factory()->create();
        $area = ServiceArea::query()->create(['name' => 'Area Tidak Aktif', 'is_active' => false]);
        $rt = $this->createRt('RT-INACTIVE-AREA');
        $this->runRegionMutation(fn () => $area->rts()->sync([$rt->id]));
        StaffProfile::query()->create([
            'user_id' => $actor->id,
            'staff_number' => 'STF-'.str_pad((string) $actor->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        $customer = User::factory()->create();
        $customer->customerProfile()->create($this->customerProfileAttributes($rt));
        $scope = new VisibleUsers(new PermissionChecker);
        $this->grant($actor, 'custom', 'user.view', 'user.view.area');

        self::assertSame([$actor->id], $scope->queryFor($actor->fresh())->pluck('id')->all());
    }

    public function test_all_scope_sees_all_active_users_but_requires_user_view_and_explicit_scope(): void
    {
        $actor = User::factory()->create();
        $active = User::factory()->create();
        $inactive = User::factory()->inactive()->create();
        $scope = new VisibleUsers(new PermissionChecker);

        $this->grant($actor, 'custom', 'user.view.all');
        self::assertSame([$actor->id], $scope->queryFor($actor->fresh())->pluck('id')->all());

        $this->grant($actor, 'custom-all', 'user.view', 'user.view.all');
        self::assertSame(
            [$actor->id, $active->id],
            $scope->queryFor($actor->fresh())->orderBy('id')->pluck('id')->all(),
        );
        self::assertFalse((new UserPolicy(new PermissionChecker, $scope))->view($actor->fresh(), $inactive));
    }

    /** @return array{0: User, 1: ServiceArea, 2: Rt} */
    private function createStaffArea(string $activeFrom, ?string $activeTo, bool $areaActive, bool $rtActive): array
    {
        $actor = User::factory()->create();
        $area = ServiceArea::query()->create(['name' => 'Area '.$actor->id, 'is_active' => $areaActive]);
        $rt = $this->createRt('RT-ALLOWED-'.$actor->id, $rtActive);
        $this->runRegionMutation(fn () => $area->rts()->sync([$rt->id]));
        StaffProfile::query()->create([
            'user_id' => $actor->id,
            'staff_number' => 'STF-'.str_pad((string) $actor->id, 8, '0', STR_PAD_LEFT),
            'service_area_id' => $area->id,
            'active_from' => $activeFrom,
            'active_to' => $activeTo,
        ]);

        return [$actor, $area, $rt];
    }

    private function createRt(string $code, bool $active = true): Rt
    {
        $dusun = Dusun::query()->create(['code' => $code.'-D', 'name' => $code.' Dusun']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code.'-W', 'name' => $code.' RW']);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $code, 'is_active' => $active]);
    }

    /** @return array{rt_id: int, address: string} */
    private function customerProfileAttributes(Rt $rt): array
    {
        return ['rt_id' => $rt->id, 'address' => 'Alamat uji', 'joined_at' => today()];
    }

    private function runRegionMutation(callable $callback): mixed
    {
        return RegionMutationGuard::run($callback);
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
