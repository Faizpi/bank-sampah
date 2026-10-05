<?php

declare(strict_types=1);

namespace Tests\Feature\Citizen;

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Livewire\Citizen\DepositHistory;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

final class DepositHistoryTest extends TestCase
{
    use RefreshDatabase;

    public function test_citizen_can_filter_only_their_deposits_by_number_status_and_date_range(): void
    {
        $citizen = $this->citizenWithDepositViewPermission();
        $otherCitizen = User::factory()->create();

        $matching = $this->deposit($citizen, 'DEP-CARI-001', Deposit::STATUS_FINAL, '2026-08-10', 'penjemputan');
        $this->deposit($citizen, 'DEP-LAIN-002', Deposit::STATUS_REJECTED, '2026-08-11', 'langsung');
        $this->deposit($citizen, 'DEP-LAMA-003', Deposit::STATUS_FINAL, '2026-08-01', 'keliling');
        $this->deposit($otherCitizen, 'DEP-CARI-RAHASIA', Deposit::STATUS_FINAL, '2026-08-10', 'penjemputan');

        $this->actingAs($citizen);

        Livewire::test(DepositHistory::class)
            ->set('transactionNumber', 'CARI')
            ->set('status', Deposit::STATUS_FINAL)
            ->set('method', 'penjemputan')
            ->set('dateFrom', '2026-08-10')
            ->set('dateUntil', '2026-08-10')
            ->assertSee($matching->deposit_number)
            ->assertDontSee('DEP-LAIN-002')
            ->assertDontSee('DEP-LAMA-003')
            ->assertDontSee('DEP-CARI-RAHASIA')
            ->assertSee('Selesai')
            ->assertSee('Penjemputan');
    }

    public function test_deposit_history_exposes_all_three_channels_and_navigation_links(): void
    {
        $citizen = $this->citizenWithDepositViewPermission();
        $this->deposit($citizen, 'DEP-LANGSUNG', Deposit::STATUS_FINAL, '2026-08-10', 'langsung');
        $this->deposit($citizen, 'DEP-JEMPUT', Deposit::STATUS_FINAL, '2026-08-11', 'penjemputan');
        $this->deposit($citizen, 'DEP-KELILING', Deposit::STATUS_FINAL, '2026-08-12', 'keliling');

        Livewire::actingAs($citizen)
            ->test(DepositHistory::class)
            ->assertSee('Setor langsung')
            ->assertSee('Penjemputan')
            ->assertSee('Bank Sampah Keliling')
            ->assertSeeHtml(route('citizen.withdrawal-history'))
            ->assertSeeHtml(route('citizen.grocery-history'));
    }

    public function test_citizen_cannot_filter_history_to_another_citizens_deposit(): void
    {
        $citizen = $this->citizenWithDepositViewPermission();
        $otherCitizen = User::factory()->create();
        $this->deposit($otherCitizen, 'DEP-MILIK-ORANG-LAIN', Deposit::STATUS_FINAL, '2026-08-10');

        $this->actingAs($citizen);

        Livewire::test(DepositHistory::class)
            ->set('transactionNumber', 'MILIK-ORANG-LAIN')
            ->assertDontSee('DEP-MILIK-ORANG-LAIN')
            ->assertSee('Tidak ada setoran yang sesuai dengan filter.');
    }

    private function citizenWithDepositViewPermission(): User
    {
        $citizen = User::factory()->create();
        $role = Role::query()->create(['name' => 'citizen-history-'.str()->random(12), 'description' => 'Citizen history test role']);
        $permission = Permission::query()->firstOrCreate(['name' => 'deposit.view'], ['description' => 'View deposits']);
        $role->permissions()->attach($permission);
        $citizen->roles()->attach($role);

        return $citizen->fresh();
    }

    private function deposit(User $citizen, string $number, string $status, string $occurredAt, string $method = 'langsung'): Deposit
    {
        $date = CarbonImmutable::parse($occurredAt)->setTime(10, 0);

        return Deposit::query()->create([
            'deposit_number' => $number,
            'customer_id' => $citizen->id,
            'staff_id' => $citizen->id,
            'method' => $method,
            'occurred_at' => $date,
            'status' => $status,
            'total_weight_kg' => '1.000',
            'total_value' => 10_000,
            'finalized_at' => $status === Deposit::STATUS_FINAL ? $date : null,
        ]);
    }
}
