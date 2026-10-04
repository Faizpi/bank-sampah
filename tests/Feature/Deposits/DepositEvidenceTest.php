<?php

declare(strict_types=1);

namespace Tests\Feature\Deposits;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Actions\AssistedCustomerService;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\CustomersRegions\Models\AssistedCustomerService as AssistedCustomerServiceModel;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Deposits\Models\Deposit;
use App\Domain\Deposits\Services\DepositService;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Ledger\Models\IdempotencyKey;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\MobileServices\Enums\MobileServiceStatus;
use App\Domain\MobileServices\Models\MobileService;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Domain\WasteMaster\Actions\ManageWastePricing;
use App\Domain\WasteMaster\Models\WasteCategory;
use App\Domain\WasteMaster\Models\WasteCondition;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteUnit;
use App\Domain\WasteMaster\Support\WasteMasterMutationGuard;
use App\Livewire\Officer\Dashboard as OfficerDashboard;
use App\Livewire\Officer\DepositForm;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

final class DepositEvidenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_deposit_form_saves_a_valid_draft_without_evidence(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'customer.view', 'user.view', 'user.view.all']);

        Livewire::actingAs($staff)
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->set('items', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']])
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('draft.status', Deposit::STATUS_DRAFT);

        self::assertDatabaseCount('deposits', 1);
        self::assertDatabaseCount('media', 0);
    }

    public function test_officer_dashboard_resume_link_is_scoped_to_the_selected_draft(): void
    {
        [$staff, $customer] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.view', 'customer.view', 'user.view', 'user.view.all']);
        $draft = app(DepositService::class)->createDraft($staff, $customer);

        Livewire::actingAs($staff)
            ->test(OfficerDashboard::class)
            ->assertSee(route('officer.deposit-form', ['customerId' => $customer->id, 'draftId' => $draft->id]), false);
    }

    public function test_deposit_form_resumes_owned_draft_hydrates_items_and_finalizes_without_duplicate(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $service->replaceDraftItems($staff, $draft, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.250']]);

        $component = Livewire::actingAs($staff)
            ->withQueryParams(['draftId' => $draft->id])
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->assertSet('draft.id', $draft->id)
            ->assertSet('customerId', $customer->id)
            ->assertSet('items.0.waste_type_id', $type->id)
            ->assertSet('items.0.condition_id', $condition->id)
            ->assertSet('items.0.weight_kg', '1.250')
            ->call('saveDraft')
            ->assertHasNoErrors()
            ->assertSet('draft.id', $draft->id);

        self::assertDatabaseCount('deposits', 1);

        $component->set('evidence', UploadedFile::fake()->image('deposit-form-proof.png', 1, 1))
            ->call('reviewFinalization')
            ->assertHasNoErrors()
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('draft.id', $draft->id)
            ->assertSet('draft.status', Deposit::STATUS_FINAL);

        self::assertDatabaseCount('deposits', 1);
        self::assertDatabaseHas('deposits', ['id' => $draft->id, 'status' => Deposit::STATUS_FINAL]);
        self::assertDatabaseCount('ledger_entries', 1);
    }

    public function test_deposit_form_rejects_resume_for_another_officer(): void
    {
        [$staff, $customer] = $this->context();
        $otherStaff = User::factory()->create();
        $this->grant($staff, ['deposit.create', 'customer.view', 'user.view', 'user.view.all']);
        $this->grant($otherStaff, ['deposit.create', 'customer.view', 'user.view', 'user.view.all']);
        $draft = app(DepositService::class)->createDraft($staff, $customer);

        Livewire::actingAs($otherStaff)
            ->withQueryParams(['draftId' => $draft->id])
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->assertNotFound();
    }

    public function test_deposit_form_rejects_resume_when_customer_does_not_match_draft(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $otherCustomer = User::factory()->create();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'customer.view', 'user.view', 'user.view.all']);
        $draft = app(DepositService::class)->createDraft($staff, $customer);

        Livewire::actingAs($staff)
            ->withQueryParams(['draftId' => $draft->id])
            ->test(DepositForm::class, ['customerId' => $otherCustomer->id])
            ->assertNotFound();
    }

    public function test_deposit_form_rejects_resume_for_a_final_deposit(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $final = $service->finalize($staff, $service->createDraft($staff, $customer), 'resume-final-deposit-key-1', [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->uploadedFile('deposit-proof.png', self::png()));

        Livewire::actingAs($staff)
            ->withQueryParams(['draftId' => $final->id])
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->assertNotFound();
    }

    public function test_deposit_form_resume_preserves_mobile_service_context(): void
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'customer.view', 'user.view', 'user.view.all']);
        $mobileService = $this->mobileService($staff, $type);
        $draft = app(DepositService::class)->createDraft($staff, $customer, 'keliling', null, $mobileService);
        app(DepositService::class)->replaceDraftItems($staff, $draft, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']]);

        Livewire::actingAs($staff)
            ->withQueryParams(['draftId' => $draft->id, 'mobileServiceId' => 999999])
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->assertSet('draft.id', $draft->id)
            ->assertSet('mobileServiceId', $mobileService->id)
            ->assertSet('items.0.waste_type_id', $type->id);
    }

    public function test_deposit_form_requires_evidence_and_clears_it_after_successful_finalization(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $items = [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']];
        $proof = UploadedFile::fake()->image('deposit-form-proof.png', 1, 1);

        Livewire::actingAs($staff)
            ->test(DepositForm::class, ['customerId' => $customer->id])
            ->set('items', $items)
            ->call('reviewFinalization')
            ->assertHasErrors(['evidence' => 'required'])
            ->set('evidence', $proof)
            ->call('reviewFinalization')
            ->assertHasNoErrors()
            ->call('finalize')
            ->assertHasNoErrors()
            ->assertSet('evidence', null);

        self::assertDatabaseCount('ledger_entries', 1);
        self::assertDatabaseCount('media', 1);
    }

    public function test_assisted_finalization_rolls_back_all_state_when_handoff_validation_fails(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all', 'customer.create-assisted']);
        $owner = User::factory()->create();
        CustomerProfile::factory()->for($owner)->create(['customer_number' => 'CST-ASSISTED-ROLLBACK']);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'assisted/evidence.png',
            'original_name' => 'evidence.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'assisted-evidence'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $staff->id,
        ]);
        $record = app(AssistedCustomerService::class)->record($staff, $owner, AssistedServiceContract::create($owner->id, $staff->id, 'layanan_nasabah', Consent::given('assisted-service-v1'), EvidenceReference::privateMedia($evidence->id)));
        $draft = app(DepositService::class)->createDraft($staff, $customer);

        $this->expectException(ValidationException::class);
        try {
            app(DepositService::class)->finalizeAndLinkAssisted($staff, $draft, 'assisted-rollback-key-1', $record->id, [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']], $this->uploadedFile('deposit-proof.png', self::png()));
        } finally {
            self::assertSame(Deposit::STATUS_DRAFT, $draft->fresh()->status);
            self::assertNull($draft->fresh()->total_value);
            self::assertNull($record->depositId);
            self::assertNull(AssistedCustomerServiceModel::query()->whereKey($record->id)->value('deposit_id'));
            self::assertDatabaseCount('ledger_entries', 0);
            self::assertDatabaseCount('idempotency_keys', 0);
            self::assertDatabaseCount('media', 1);
            self::assertSame(1, AuditLog::query()->where('action', 'assisted-service.recorded')->count());
            self::assertSame(0, AuditLog::query()->where('action', 'deposit.finalized')->count());
            self::assertSame(0, AuditLog::query()->where('action', 'assisted-service.deposit-linked')->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_assisted_finalization_links_once_and_retry_does_not_duplicate_ledger_or_audit(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all', 'customer.create-assisted']);
        $evidence = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'assisted/success-evidence.png',
            'original_name' => 'success-evidence.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'success-evidence'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $staff->id,
        ]);
        $record = app(AssistedCustomerService::class)->record($staff, $customer, AssistedServiceContract::create($customer->id, $staff->id, 'layanan_nasabah', Consent::given('assisted-service-v1'), EvidenceReference::privateMedia($evidence->id)));
        $draft = app(DepositService::class)->createDraft($staff, $customer);
        $items = [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']];

        $service = app(DepositService::class);
        $first = $service->finalizeAndLinkAssisted($staff, $draft, 'assisted-success-key-1', $record->id, $items, $this->uploadedFile('deposit-proof.png', self::png()));
        $retry = $service->finalizeAndLinkAssisted($staff, $draft, 'assisted-success-key-1', $record->id, $items, $this->uploadedFile('deposit-proof.png', self::png()));

        self::assertSame($first->id, $retry->id);
        self::assertSame($first->id, AssistedCustomerServiceModel::query()->whereKey($record->id)->value('deposit_id'));
        self::assertSame(1, LedgerEntry::query()->where('source_type', Deposit::class)->where('source_id', $first->id)->count(), 'ledger');
        self::assertSame(1, AuditLog::query()->where('action', 'deposit.finalized')->count(), 'deposit audit: '.json_encode(AuditLog::query()->pluck('action')->all(), JSON_THROW_ON_ERROR));
        self::assertSame(1, AuditLog::query()->where('action', 'assisted-service.deposit-linked')->count(), 'link audit: '.json_encode(AuditLog::query()->pluck('action')->all(), JSON_THROW_ON_ERROR));
        self::assertSame(1, IdempotencyKey::query()->where('key', 'assisted-success-key-1')->count(), 'idempotency');
        self::assertSame(1, Media::query()->where('attachable_type', Deposit::class)->where('attachable_id', $first->id)->count(), 'media');
    }

    public function test_direct_finalization_requires_evidence_before_any_financial_effect(): void
    {
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $items = [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']];

        $this->expectException(ValidationException::class);
        try {
            $service->finalize($staff, $draft, 'deposit-evidence-missing-1', $items);
        } finally {
            self::assertSame(Deposit::STATUS_DRAFT, $draft->fresh()->status);
            self::assertDatabaseCount('ledger_entries', 0);
            self::assertDatabaseCount('media', 0);
        }
    }

    public function test_direct_finalization_links_signature_verified_evidence_to_private_media(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $proof = $this->uploadedFile('deposit-proof.png', self::png());

        $final = $service->finalize($staff, $draft, 'deposit-evidence-private-1', [
            ['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000'],
        ], $proof);

        $media = $final->media()->sole();
        self::assertSame(Media::class, $media::class);
        self::assertSame(Deposit::class, $media->attachable_type);
        self::assertSame($final->id, $media->attachable_id);
        self::assertSame('private', $media->getRawOriginal('visibility'));
        self::assertSame('media_private', $media->disk);
        self::assertStringNotContainsString(public_path(), $media->path);
        Storage::disk('media_private')->assertExists($media->path);
        self::assertSame(1, $final->ledgerEntries()->count());
    }

    public function test_invalid_evidence_is_rejected_without_metadata_or_private_file(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);

        $this->expectException(ValidationException::class);
        try {
            $service->finalize($staff, $draft, 'deposit-evidence-invalid-1', [
                ['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000'],
            ], $this->uploadedFile('deposit-proof.svg', '<svg></svg>', 'image/svg+xml'));
        } finally {
            self::assertDatabaseCount('media', 0);
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_evidence_upload_respects_draft_owner_scope_before_storage(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $otherStaff = User::factory()->create();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $this->grant($otherStaff, ['deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);

        $this->expectException(AuthorizationException::class);
        try {
            $service->finalize($otherStaff, $draft, 'deposit-evidence-scope-1', [
                ['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000'],
            ], $this->uploadedFile('deposit-proof.png', self::png()));
        } finally {
            self::assertDatabaseCount('media', 0);
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_retry_returns_the_same_finalization_and_does_not_store_evidence_twice(): void
    {
        Storage::fake('media_private');
        [$staff, $customer, $type, $condition] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);
        $items = [['waste_type_id' => $type->id, 'condition_id' => $condition->id, 'weight_kg' => '1.000']];
        $proof = $this->uploadedFile('deposit-proof.png', self::png());

        $first = $service->finalize($staff, $draft, 'deposit-evidence-retry-1', $items, $proof);
        $retry = $service->finalize($staff, $draft, 'deposit-evidence-retry-1', $items, $proof);

        self::assertSame($first->id, $retry->id);
        self::assertSame(1, Media::query()->where('attachable_type', Deposit::class)->where('attachable_id', $first->id)->count());
        self::assertSame(1, $first->ledgerEntries()->count());
    }

    public function test_failed_finalization_cleans_up_evidence_written_before_transaction_failure(): void
    {
        Storage::fake('media_private');
        [$staff, $customer] = $this->pricedContext();
        $this->grant($staff, ['deposit.create', 'deposit.update-draft', 'deposit.finalize', 'customer.view', 'user.view', 'user.view.all']);
        $service = app(DepositService::class);
        $draft = $service->createDraft($staff, $customer);

        $this->expectException(ValidationException::class);
        try {
            $service->finalize($staff, $draft, 'deposit-evidence-cleanup-1', [
                ['waste_type_id' => 999999, 'condition_id' => 999999, 'weight_kg' => '1.000'],
            ], $this->uploadedFile('deposit-proof.png', self::png()));
        } finally {
            self::assertDatabaseCount('media', 0);
            Storage::disk('media_private')->assertDirectoryEmpty('/');
            self::assertSame(Deposit::STATUS_DRAFT, $draft->fresh()->status);
        }
    }

    /** @return array{User, User, WasteType, WasteCondition} */
    private function pricedContext(): array
    {
        [$staff, $customer, $type, $condition] = $this->context();
        $manager = User::factory()->create();
        $this->grant($manager, ['price.manage']);
        app(ManageWastePricing::class)->createPeriod($manager, $type, $condition, 3_000, CarbonImmutable::parse('2026-08-01', 'Asia/Jakarta'), null, (string) str()->uuid());

        return [$staff, $customer, $type, $condition];
    }

    /** @return array{User, User, WasteType, WasteCondition} */
    private function context(): array
    {
        $staff = User::factory()->create();
        $customer = User::factory()->create();
        $category = WasteCategory::factory()->create();
        $unit = WasteUnit::factory()->weight()->create(['code' => 'KG', 'symbol' => 'kg']);
        $condition = WasteCondition::factory()->create();
        $type = WasteType::factory()->for($category, 'category')->for($unit, 'unit')->create();
        WasteMasterMutationGuard::run(fn (): array => $type->conditions()->sync([$condition->id]));
        $dusun = Dusun::query()->create(['code' => 'EV-DS-'.$customer->id, 'name' => 'Dusun Evidence']);
        $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'EV-RW-'.$customer->id, 'name' => 'RW Evidence']);
        $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'EV-RT-'.$customer->id, 'name' => 'RT Evidence']);
        $customer->customerProfile()->create(['rt_id' => $rt->id, 'address' => 'Alamat pengujian']);

        return [$staff, $customer, $type, $condition];
    }

    private function mobileService(User $staff, WasteType $type): MobileService
    {
        $service = MobileService::query()->create([
            'service_number' => 'MOB-RESUME-'.str()->upper(str()->random(10)),
            'point' => 'Titik layanan resume',
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
            'status' => MobileServiceStatus::Open,
            'capacity' => 20,
            'created_by' => $staff->id,
        ]);
        $service->staff()->attach($staff->id);
        $service->wasteTypes()->attach($type->id);

        return $service->fresh();
    }

    /** @param list<string> $names */
    private function grant(User $user, array $names): void
    {
        $role = Role::query()->create(['name' => 'evidence-role-'.$user->id.'-'.str()->random(5), 'description' => 'Deposit evidence']);
        foreach ($names as $name) {
            $permission = Permission::query()->firstOrCreate(['name' => $name], ['description' => $name]);
            $role->permissions()->attach($permission);
        }
        $user->roles()->attach($role);
    }

    private function uploadedFile(string $name, string $contents, string $clientMimeType = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'deposit-evidence-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $clientMimeType, null, true);
    }

    private static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true) ?: throw new \LogicException('Invalid PNG fixture.');
    }
}
