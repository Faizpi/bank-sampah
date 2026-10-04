<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\AssistedCustomerService;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\CustomersRegions\Support\RegionMutationGuard;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Identity\Models\StaffProfile;
use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class AssistedCustomerServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_operator_records_customer_as_owner_with_separate_consent_and_private_evidence(): void
    {
        $operator = User::factory()->create(['name' => 'Petugas Lapangan']);
        $owner = User::factory()->create(['name' => 'Warga Tanpa Smartphone']);
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-12345678']);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'evidence/receipt.png',
            'original_name' => 'receipt.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'evidence'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $operator->id,
        ]);
        $this->grant($operator, 'customer.create-assisted', 'customer.view', 'user.view', 'user.view.all');

        $contract = AssistedServiceContract::create(
            $owner->id,
            $operator->id,
            'layanan_nasabah',
            Consent::given('assisted-service-v1'),
            EvidenceReference::privateMedia($evidence->id),
        );

        $record = app(AssistedCustomerService::class)->record($operator, $owner, $contract);

        self::assertSame($owner->id, $record->ownerId);
        self::assertSame($operator->id, $record->operatorId);
        self::assertSame('assisted-service-v1', $record->consentVersion);
        self::assertSame($evidence->id, $record->evidenceMediaId);
        self::assertDatabaseHas('assisted_customer_services', [
            'owner_id' => $owner->id,
            'operator_id' => $operator->id,
            'consent_version' => 'assisted-service-v1',
            'evidence_media_id' => $evidence->id,
        ]);
    }

    public function test_assisted_handoff_links_final_receipt_and_current_balance_without_password_fields(): void
    {
        $operator = User::factory()->create(['name' => 'Petugas Handoff']);
        $owner = User::factory()->create(['name' => 'Warga Handoff']);
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-HANDOFF-001']);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'evidence/handoff.png',
            'original_name' => 'handoff.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'handoff'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $operator->id,
        ]);
        $this->grant($operator, 'customer.create-assisted', 'customer.view', 'user.view', 'user.view.all');
        $record = app(AssistedCustomerService::class)->record($operator, $owner, AssistedServiceContract::create($owner->id, $operator->id, 'layanan_nasabah', Consent::given('assisted-service-v1'), EvidenceReference::privateMedia($evidence->id)));
        $deposit = Deposit::query()->create([
            'deposit_number' => 'DEP-HANDOFF-001',
            'customer_id' => $owner->id,
            'staff_id' => $operator->id,
            'method' => 'langsung',
            'occurred_at' => now(),
            'status' => Deposit::STATUS_FINAL,
            'total_weight_kg' => '1.000',
            'total_value' => 12_000,
            'finalized_at' => now(),
        ]);
        $account = LedgerAccount::query()->create(['user_id' => $owner->id, 'status' => 'aktif', 'currency' => 'IDR']);
        LedgerEntry::query()->create(['entry_number' => 'LED-HANDOFF-001', 'ledger_account_id' => $account->id, 'direction' => LedgerEntry::DIRECTION_IN, 'kind' => LedgerEntry::KIND_DEPOSIT, 'amount' => 12_000, 'source_type' => Deposit::class, 'source_id' => $deposit->id, 'source_key' => 'handoff-ledger-001', 'effective_at' => now(), 'balance_after' => 12_000]);

        $service = app(AssistedCustomerService::class);
        $service->linkDeposit($operator, $record->id, $deposit);
        $handoff = $service->handoff($operator, $record->id);

        self::assertSame($owner->id, $handoff->ownerId);
        self::assertSame($operator->id, $handoff->operatorId);
        self::assertSame($deposit->id, $handoff->depositId);
        self::assertSame('DEP-HANDOFF-001', $handoff->receipt['number']);
        self::assertSame(12_000, $handoff->availableBalance);
        self::assertArrayNotHasKey('password', $handoff->receipt);
    }

    public function test_missing_consent_public_evidence_self_service_and_password_fields_are_rejected(): void
    {
        $operator = User::factory()->create();
        $owner = User::factory()->create();
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-12345678']);
        $this->grant($operator, 'customer.create-assisted', 'customer.view', 'user.view', 'user.view.all');

        $this->expectException(ValidationException::class);
        app(AssistedCustomerService::class)->record(
            $operator,
            $owner,
            AssistedServiceContract::create(
                $owner->id,
                $operator->id,
                'layanan_nasabah',
                Consent::given('assisted-service-v1'),
                EvidenceReference::privateMedia(999),
            ),
        );
    }

    public function test_unprivileged_operator_cannot_record_assisted_service(): void
    {
        $operator = User::factory()->create();
        $owner = User::factory()->create();
        $this->expectException(AuthorizationException::class);
        app(AssistedCustomerService::class)->record(
            $operator,
            $owner,
            AssistedServiceContract::create(
                $owner->id,
                $operator->id,
                'layanan_nasabah',
                Consent::given('assisted-service-v1'),
                EvidenceReference::privateMedia(1),
            ),
        );
    }

    public function test_area_operator_cannot_record_assisted_service_for_a_customer_in_another_area(): void
    {
        [$operator, $owner, $evidence] = $this->scopedFixture();
        $outside = User::factory()->create();
        CustomerProfile::factory()->for($outside)->create(['customer_number' => 'CST-87654321', 'rt_id' => $this->createRt('RT-OUTSIDE')->id]);
        $contract = AssistedServiceContract::create(
            $outside->id,
            $operator->id,
            'layanan_nasabah',
            Consent::given('assisted-service-v1'),
            EvidenceReference::privateMedia($evidence->id),
        );

        $this->expectException(AuthorizationException::class);
        app(AssistedCustomerService::class)->record($operator, $outside, $contract);
    }

    /** @return array{User, User, Media} */
    private function scopedFixture(): array
    {
        $operator = User::factory()->create();
        $owner = User::factory()->create();
        [$area, $rt] = $this->areaWithRt();
        StaffProfile::factory()->for($operator)->create(['service_area_id' => $area->id]);
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-12345678', 'rt_id' => $rt->id]);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'evidence/scoped-receipt.png',
            'original_name' => 'scoped-receipt.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'scoped-evidence'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $operator->id,
        ]);
        $this->grant($operator, 'customer.create-assisted', 'customer.view', 'user.view', 'user.view.area');

        return [$operator, $owner, $evidence];
    }

    /** @return array{ServiceArea, Rt} */
    private function areaWithRt(): array
    {
        $dusun = Dusun::query()->create(['code' => 'DS-SCOPED', 'name' => 'Dusun Scoped']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-SCOPED', 'name' => 'RW Scoped']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'RT-SCOPED', 'name' => 'RT Scoped']);
        $area = ServiceArea::query()->create(['name' => 'Area Scoped']);
        RegionMutationGuard::run(fn () => $area->rts()->sync([$rt->id]));

        return [$area, $rt];
    }

    private function createRt(string $code): Rt
    {
        $dusun = Dusun::query()->create(['code' => $code.'-D', 'name' => $code.' Dusun']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => $code.'-W', 'name' => $code.' RW']);

        return Rt::query()->create(['rw_id' => $rw->id, 'code' => $code, 'name' => $code]);
    }

    private function grant(User $user, string ...$permissions): void
    {
        $role = Role::query()->create(['name' => 'w2-assisted-'.fake()->unique()->numerify('####'), 'description' => 'W2 assisted test role']);
        foreach ($permissions as $permissionName) {
            $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => "W2 {$permissionName}"]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }
}
