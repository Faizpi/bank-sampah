<?php

declare(strict_types=1);

namespace Tests\Feature\Deposits;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Deposits\Actions\RotateDepositVerificationToken;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class DepositVerificationRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_backoffice_actor_rotates_deposit_verification_qr_and_invalidates_old_token(): void
    {
        $actor = $this->userWith('qr-verification.rotate', 'deposit.view', 'user.view', 'user.view.all');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        $oldToken = QrToken::generate();
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-QR-ROTATE-001',
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000',
            'total_value' => 10_000,
            'finalized_at' => now(),
            'verification_token_hash' => $oldToken->hash(),
            'verification_token_encrypted' => $oldToken->value(),
        ]);

        $rotated = app(RotateDepositVerificationToken::class)->handle($actor, $deposit, 'QR lama diduga tersalin oleh pihak lain.');
        $newToken = QrToken::fromValue((string) $rotated->fresh()->verificationToken());

        self::assertFalse($oldToken->matches($newToken));
        self::assertNotSame($oldToken->hash(), $rotated->fresh()->getRawOriginal('verification_token_hash'));
        $this->actingAs($actor)->get(route('public.deposit-verification', ['token' => $oldToken->value()]))->assertNotFound();
        $this->actingAs($actor)->get(route('public.deposit-verification', ['token' => $newToken->value()]))->assertOk()->assertSee('Bukti setoran valid');
        $audit = AuditLog::query()->where('action', 'deposit.verification_qr_rotated')->latest('id')->firstOrFail();
        self::assertSame('QR lama diduga tersalin oleh pihak lain.', $audit->new_values['reason']);
        self::assertStringNotContainsString($oldToken->value(), json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
        self::assertStringNotContainsString($newToken->value(), json_encode($audit->toArray(), JSON_THROW_ON_ERROR));
    }

    public function test_actor_without_rotation_permission_is_rejected(): void
    {
        $actor = $this->userWith('deposit.view', 'user.view', 'user.view.all');
        $customer = User::factory()->create();
        CustomerProfile::factory()->for($customer)->create();
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-QR-ROTATE-002',
            'customer_id' => $customer->id,
            'staff_id' => $actor->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000',
            'total_value' => 10_000,
            'finalized_at' => now(),
            'verification_token_hash' => QrToken::generate()->hash(),
        ]);

        $this->expectException(AuthorizationException::class);
        app(RotateDepositVerificationToken::class)->handle($actor, $deposit, 'Alasan rotasi tidak berwenang.');
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'qr-rotation-test-'.$user->id, 'description' => 'QR rotation test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
