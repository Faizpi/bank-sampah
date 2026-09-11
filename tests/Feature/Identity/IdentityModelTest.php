<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\DatabaseSession;
use App\Domain\Identity\Models\PasswordResetToken;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IdentityModelTest extends TestCase
{
    use RefreshDatabase;

    public function test_models_cast_hide_and_relate_identity_data(): void
    {
        $user = User::factory()->create();
        $profile = CustomerProfile::factory()->for($user)->create();
        $staff = StaffProfile::factory()->for($user)->create();
        DatabaseSession::query()->forceCreate(['id' => 's1', 'user_id' => $user->id, 'payload' => 'secret', 'last_activity' => 1]);
        PasswordResetToken::query()->forceCreate(['email' => 'reset@example.test', 'user_id' => $user->id, 'token' => 'hash']);

        expect($user->status)->toBe(UserStatus::Active)
            ->and($user->verified_at)->toBeInstanceOf(CarbonImmutable::class)
            ->and($user->customerProfile->is($profile))->toBeTrue()
            ->and($user->staffProfile->is($staff))->toBeTrue()
            ->and($profile->rt->customerProfiles->contains($profile))->toBeTrue()
            ->and($staff->serviceArea->staffProfiles->contains($staff))->toBeTrue()
            ->and($staff->serviceAreas)->toHaveCount(1)
            ->and($staff->serviceAreas->sole()->service_area_id)->toBe($staff->service_area_id)
            ->and(StaffServiceArea::query()->where('staff_profile_user_id', $user->id)->where('service_area_id', $staff->service_area_id)->exists())->toBeTrue()
            ->and($user->databaseSessions)->toHaveCount(1)
            ->and($user->passwordResetTokens)->toHaveCount(1);

        expect(array_keys($user->toArray()))->not->toContain('password', 'remember_token', 'phone', 'email', 'terms_version', 'terms_accepted_at');
        expect(array_keys($profile->toArray()))->not->toContain('address', 'qr_token_hash');
        expect(array_keys($user->databaseSessions->firstOrFail()->toArray()))->not->toContain('payload', 'ip_address', 'ip_address_hash', 'user_agent');
        expect(array_keys($user->passwordResetTokens->firstOrFail()->toArray()))->not->toContain('email', 'phone', 'token');
    }

    public function test_generic_mass_assignment_cannot_change_status_or_terms_acceptance(): void
    {
        $user = User::factory()->create();
        $user->fill([
            'name' => 'Updated Name',
            'status' => UserStatus::Rejected,
            'terms_version' => 'unsafe-v2',
            'terms_accepted_at' => now()->addDay(),
        ]);

        $termsAcceptedAt = $user->terms_accepted_at;
        self::assertInstanceOf(CarbonImmutable::class, $termsAcceptedAt);

        self::assertSame('Updated Name', $user->name);
        self::assertSame(UserStatus::Active, $user->status);
        self::assertSame('test-v1', $user->terms_version);
        self::assertFalse($termsAcceptedAt->isFuture());
    }

    public function test_factory_states_are_consistent_and_soft_deletes_preserve_users(): void
    {
        $pending = User::factory()->pendingVerification()->create();
        $rejected = User::factory()->rejected()->create();
        $inactive = User::factory()->inactive()->create();

        expect($pending->status)->toBe(UserStatus::PendingVerification)->and($pending->verified_at)->toBeNull()
            ->and($rejected->status)->toBe(UserStatus::Rejected)->and($rejected->rejection_reason)->not->toBeNull()
            ->and($inactive->status)->toBe(UserStatus::Inactive);

        $inactive->delete();
        $this->assertSoftDeleted($inactive);
        expect(User::withTrashed()->find($inactive->id))->not->toBeNull();
    }
}
