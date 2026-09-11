<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Actions\ManageRegions;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class CustomerIdentityTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_actor_issues_immutable_number_and_hashed_qr_token_for_active_customer(): void
    {
        $actor = $this->authorizedActor('customer.card.issue', 'customer.view', 'user.view', 'user.view.all');
        $customer = User::factory()->create(['name' => 'Siti Aminah']);
        $profile = CustomerProfile::factory()->for($customer)->create([
            'customer_number' => null,
            'qr_token_hash' => null,
            'joined_at' => null,
        ]);

        $issued = app(ManageCustomerIdentity::class)->issue($actor, $customer->fresh());
        $profile->refresh();

        self::assertMatchesRegularExpression('/^CST-[0-9]{8}$/', $issued['number']->value());
        self::assertMatchesRegularExpression('/^[A-Za-z0-9_-]{43}$/', $issued['token']->value());
        self::assertSame($issued['number']->value(), $profile->customer_number);
        self::assertSame(hash('sha256', $issued['token']->value()), $profile->qr_token_hash);
        self::assertNotSame($issued['token']->value(), $profile->qr_token_hash);
        self::assertNotNull($profile->joined_at);

        $this->expectException(ValidationException::class);
        app(ManageCustomerIdentity::class)->issue($actor, $customer->fresh());
    }

    public function test_rotation_invalidates_old_token_and_scan_returns_only_candidate_summary(): void
    {
        $actor = $this->authorizedActor('customer.card.issue', 'customer.qr.rotate', 'customer.view', 'user.view', 'user.view.all');
        $customer = User::factory()->create(['name' => 'Siti Aminah']);
        $profile = CustomerProfile::factory()->for($customer)->create([
            'customer_number' => null,
            'qr_token_hash' => null,
            'joined_at' => null,
        ]);

        $issued = app(ManageCustomerIdentity::class)->issue($actor, $customer->fresh());
        $rotated = app(ManageCustomerIdentity::class)->rotateQr($actor, $customer->fresh());
        $scanner = app(ManageCustomerIdentity::class);

        $candidate = $scanner->scan($actor, $rotated['token']->value());

        self::assertSame($customer->id, $candidate->userId);
        self::assertSame('Siti Aminah', $candidate->name);
        self::assertSame($rotated['number']->value(), $candidate->number->value());
        self::assertNotSame($issued['token']->value(), $rotated['token']->value());
        self::assertSame(hash('sha256', $rotated['token']->value()), $profile->refresh()->qr_token_hash);
        self::assertSame($rotated['token']->value(), $profile->refresh()->qr_token_encrypted);

        try {
            $scanner->scan($actor, $issued['token']->value());
            self::fail('The rotated QR token must no longer scan.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('token', $exception->errors());
        }
    }

    public function test_area_operator_scans_an_active_customer_through_an_effective_rt_assignment(): void
    {
        $dusun = Dusun::query()->create(['code' => 'DS-QR', 'name' => 'Dusun QR']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-QR', 'name' => 'RW QR']);
        $insideRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-IN', 'name' => 'RT Dalam']);
        $outsideRt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-OUT', 'name' => 'RT Luar']);
        $insideArea = ServiceArea::query()->create(['name' => 'Area QR Efektif']);
        $outsideArea = ServiceArea::query()->create(['name' => 'Area QR Lama']);
        $regionManager = $this->authorizedActor('region.manage');
        app(ManageRegions::class)->updateServiceArea($regionManager, $insideArea, $insideArea->name, [$insideRt]);
        app(ManageRegions::class)->updateServiceArea($regionManager, $outsideArea, $outsideArea->name, [$outsideRt]);

        $actor = $this->authorizedActor('customer.view', 'user.view', 'user.view.area');
        StaffProfile::factory()->for($actor)->create(['service_area_id' => $outsideArea->id]);
        StaffServiceArea::query()->create([
            'staff_profile_user_id' => $actor->id,
            'service_area_id' => $insideArea->id,
            'active_from' => today()->subDay(),
            'active_to' => today()->addDay(),
        ]);
        $customer = User::factory()->create(['name' => 'Warga QR Efektif']);
        $token = QrToken::generate();
        CustomerProfile::factory()->for($customer)->create([
            'customer_number' => 'CST-90757568',
            'rt_id' => $insideRt->id,
            'qr_token_hash' => $token->hash(),
        ]);

        $candidate = app(ManageCustomerIdentity::class)->scan($actor->fresh(), $token->value());

        self::assertSame($customer->id, $candidate->userId);
        self::assertSame('CST-90757568', $candidate->number->value());
    }

    public function test_invalid_token_non_customer_and_out_of_scope_scan_are_rejected(): void
    {
        $actor = $this->authorizedActor('customer.view', 'user.view.all');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create([
            'qr_token_hash' => hash('sha256', QrToken::generate()->value()),
        ]);
        $scanner = app(ManageCustomerIdentity::class);

        try {
            $scanner->scan($actor, 'invalid-token');
            self::fail('Malformed QR tokens must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('token', $exception->errors());
        }

        $unprivileged = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        $scanner->scan($unprivileged, QrToken::generate()->value());
    }

    private function authorizedActor(string ...$permissions): User
    {
        $actor = User::factory()->create();
        $role = Role::query()->create(['name' => 'w2-identity-'.fake()->unique()->numerify('####'), 'description' => 'W2 identity test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->attach($permission);
        }
        $actor->roles()->attach($role);

        return $actor->fresh();
    }
}
