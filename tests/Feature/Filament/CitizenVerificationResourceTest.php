<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Domain\Identity\Models\CustomerProfile;
use App\Filament\Resources\Identity\Models\CitizenVerifications\Pages\ManageCitizenVerifications;
use App\Models\User;
use Filament\Actions\Testing\TestAction;
use Filament\Navigation\NavigationManager;
use Filament\Panel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\RoleFixtures;
use Tests\TestCase;

final class CitizenVerificationResourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pending_queue_lists_only_pending_citizens(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'data-master', 'backoffice.access', 'user.view');
        $pendingCitizen = User::factory()->pendingVerification()->create();
        CustomerProfile::factory()->for($pendingCitizen)->create();
        $activeCitizen = User::factory()->create();
        CustomerProfile::factory()->for($activeCitizen)->create();
        $pendingNonCitizen = User::factory()->pendingVerification()->create();

        $this->actingAs($actor);

        Livewire::test(ManageCitizenVerifications::class)
            ->assertOk()
            ->assertCanSeeTableRecords([$pendingCitizen])
            ->assertCanNotSeeTableRecords([$activeCitizen, $pendingNonCitizen])
            ->assertCountTableRecords(1);
    }

    public function test_authorized_user_can_verify_a_pending_citizen_through_the_domain_action(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'data-master', 'backoffice.access', 'user.view', 'user.verify');
        $citizen = User::factory()->pendingVerification()->create();
        CustomerProfile::factory()->for($citizen)->create();
        $this->actingAs($actor);

        Livewire::test(ManageCitizenVerifications::class)
            ->assertActionVisible(TestAction::make('verify')->table($citizen))
            ->callAction(TestAction::make('verify')->table($citizen))
            ->assertNotified();

        $this->assertDatabaseHas('users', [
            'id' => $citizen->id,
            'status' => 'aktif',
            'verified_by' => $actor->id,
            'rejection_reason' => null,
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'identity.user.verified',
            'auditable_id' => $citizen->id,
        ]);
    }

    public function test_authorized_user_must_supply_a_reason_to_reject_a_pending_citizen(): void
    {
        $actor = User::factory()->create();
        $this->grant($actor, 'data-master', 'backoffice.access', 'user.view', 'user.reject');
        $citizen = User::factory()->pendingVerification()->create();
        CustomerProfile::factory()->for($citizen)->create();

        $this->actingAs($actor);

        Livewire::test(ManageCitizenVerifications::class)
            ->assertActionVisible(TestAction::make('reject')->table($citizen))
            ->callAction(TestAction::make('reject')->table($citizen), data: [])
            ->assertHasActionErrors(['rejection_reason' => 'required']);

        $this->assertDatabaseHas('users', ['id' => $citizen->id, 'status' => 'menunggu_verifikasi']);

        Livewire::test(ManageCitizenVerifications::class)
            ->callAction(TestAction::make('reject')->table($citizen), data: ['rejection_reason' => 'Alamat domisili tidak dapat diverifikasi.'])
            ->assertNotified();

        $this->assertDatabaseHas('users', [
            'id' => $citizen->id,
            'status' => 'ditolak',
            'rejection_reason' => 'Alamat domisili tidak dapat diverifikasi.',
        ]);
        $this->assertDatabaseHas('audit_logs', [
            'actor_id' => $actor->id,
            'action' => 'identity.user.rejected',
            'auditable_id' => $citizen->id,
        ]);
    }

    /** @return list<string> */
    private function dataMasterNavigationLabels(Panel $panel): array
    {
        app()->forgetInstance(NavigationManager::class);

        foreach ($panel->getNavigation() as $group) {
            if ($group->getLabel() !== 'Data Master') {
                continue;
            }

            $labels = [];

            foreach ($group->getItems() as $item) {
                $labels[] = $item->getLabel();

                foreach ($item->getChildItems() as $childItem) {
                    $labels[] = $childItem->getLabel();
                }
            }

            sort($labels);

            return $labels;
        }

        return [];
    }

    private function grant(User $user, string $roleName, string ...$permissionNames): void
    {
        $user = RoleFixtures::assign($user, $permissionNames, $roleName);
    }
}
