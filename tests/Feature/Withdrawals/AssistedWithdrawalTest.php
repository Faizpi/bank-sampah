<?php

declare(strict_types=1);

namespace Tests\Feature\Withdrawals;

use App\Domain\CustomersRegions\Actions\AssistedCustomerService;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Livewire\Officer\CustomerIdentification;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

final class AssistedWithdrawalTest extends TestCase
{
    use RefreshDatabase;

    public function test_assisted_withdrawal_persists_consent_evidence_and_durable_withdrawal_link(): void
    {
        $operator = $this->userWith('customer.create-assisted', 'customer.view', 'user.view', 'user.view.all', 'withdrawal.request');
        $owner = User::factory()->create(['name' => 'Warga pencairan berbantuan']);
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-ASSISTED-WDR']);
        $this->credit($owner, 100_000);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'evidence/assisted-withdrawal.png',
            'original_name' => 'assisted-withdrawal.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'assisted-withdrawal'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $operator->id,
        ]);
        $record = app(AssistedCustomerService::class)->record(
            $operator,
            $owner,
            AssistedServiceContract::create(
                $owner->id,
                $operator->id,
                'pencairan',
                Consent::given('assisted-withdrawal-v1'),
                EvidenceReference::privateMedia($evidence->id),
            ),
        );

        $withdrawal = app(WithdrawalService::class)->request($operator, [
            'customer_id' => $owner->id,
            'amount' => '40000',
            'pickup_location' => 'Balai warga',
            'pickup_date' => today()->addDay()->toDateString(),
            'assisted_service_id' => $record->id,
        ], 'assisted-withdrawal-key-0001');

        self::assertSame($operator->id, $withdrawal->requested_by_id);
        self::assertSame($owner->id, $withdrawal->customer_id);
        self::assertSame($withdrawal->id, $withdrawal->fresh()->assistedService?->withdrawal_id);
        self::assertDatabaseHas('assisted_customer_services', [
            'id' => $record->id,
            'service_type' => 'pencairan',
            'evidence_media_id' => $evidence->id,
            'withdrawal_id' => $withdrawal->id,
        ]);
        self::assertDatabaseHas('withdrawal_requests', ['id' => $withdrawal->id, 'requested_by_id' => $operator->id]);
        self::assertSame(60_000, $owner->ledgerAccount()->firstOrFail()->fresh()->availableBalance());
    }

    public function test_assisted_withdrawal_form_blocks_an_amount_above_the_customer_balance_before_recording_consent(): void
    {
        $operator = $this->userWith('customer.create-assisted', 'customer.view', 'user.view', 'user.view.all', 'withdrawal.request');
        $owner = User::factory()->create(['name' => 'Warga Saldo Terbatas']);
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-00009999']);
        $this->credit($owner, 20_000);

        Livewire::actingAs($operator)
            ->test(CustomerIdentification::class)
            ->set('search', 'CST-00009999')
            ->call('find')
            ->call('confirm')
            ->call('chooseService', 'withdrawal')
            ->assertSee('Saldo tersedia warga')
            ->assertSee('Rp20.000')
            ->set('withdrawalAmount', '30000')
            ->assertHasErrors(['withdrawalAmount'])
            ->set('withdrawalLocation', 'Balai warga')
            ->set('withdrawalConsent', true)
            ->set('withdrawalEvidence', UploadedFile::fake()->image('consent.jpg'))
            ->call('requestAssistedWithdrawal')
            ->assertHasErrors(['withdrawalAmount']);

        self::assertDatabaseCount('assisted_customer_services', 0);
        self::assertDatabaseCount('withdrawal_requests', 0);
    }

    private function credit(User $owner, int $amount): void
    {
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create([
            'entry_number' => 'LED-ASSISTED-WDR-'.$owner->id,
            'ledger_account_id' => $account->id,
            'direction' => LedgerEntry::DIRECTION_IN,
            'kind' => LedgerEntry::KIND_DEPOSIT,
            'amount' => $amount,
            'source_type' => User::class,
            'source_id' => $owner->id,
            'source_key' => 'assisted-withdrawal-credit-'.$owner->id,
            'effective_at' => now(),
            'balance_after' => $amount,
        ]);
    }

    private function userWith(string ...$permissions): User
    {
        $user = User::factory()->create();
        $role = Role::query()->create(['name' => 'assisted-withdrawal-test-'.$user->id, 'description' => 'Assisted withdrawal test']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);

        return $user;
    }
}
