<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Actions\AssistedCustomerService;
use App\Domain\CustomersRegions\Contracts\AssistedServiceContract;
use App\Domain\CustomersRegions\Contracts\Consent;
use App\Domain\CustomersRegions\Contracts\EvidenceReference;
use App\Domain\Identity\Models\CustomerProfile;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
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
