<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\ManageCustomerIdentity;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CustomerQrStorageTest extends TestCase
{
    use RefreshDatabase;

    public function test_issued_qr_token_is_encrypted_at_rest_and_hidden_from_model_serialization(): void
    {
        $actor = User::factory()->create();
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create(['customer_number' => null, 'qr_token_hash' => null]);
        $this->grant($actor, 'customer.card.issue', 'customer.view', 'user.view', 'user.view.all');

        $issued = app(ManageCustomerIdentity::class)->issue($actor, $customer->fresh());
        $profile = $customer->fresh()->customerProfile;

        self::assertNotNull($profile);
        self::assertSame($issued['token']->value(), $profile->qr_token_encrypted);
        $rawDatabaseValue = (string) $profile->getRawOriginal('qr_token_encrypted');
        self::assertNotSame($issued['token']->value(), $rawDatabaseValue);
        self::assertStringNotContainsString($issued['token']->value(), json_encode($profile->toArray(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($issued['token']->value(), var_export($profile, true));
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->create(['name' => 'w2-qr-storage-'.fake()->unique()->numerify('####'), 'description' => 'W2 QR storage test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
