<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
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
