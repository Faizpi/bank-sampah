<?php

declare(strict_types=1);

namespace Tests\Feature\CustomersRegions;

use App\Domain\CustomersRegions\Models\AssistedCustomerService;
use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

final class AssistedCustomerServiceSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_assisted_service_schema_keeps_owner_operator_evidence_and_append_only_invariants(): void
    {
        $operator = User::factory()->create();
        $owner = User::factory()->create();
        $media = Media::query()->create([
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => 'evidence/schema.png',
            'original_name' => 'schema.png',
            'mime_type' => 'image/png',
            'size' => 10,
            'checksum' => hash('sha256', 'schema'),
            'visibility' => MediaVisibility::Private,
            'uploader_id' => $operator->id,
        ]);
        $attributes = [
            'owner_id' => $owner->id,
            'operator_id' => $operator->id,
            'service_type' => 'layanan_nasabah',
            'consent_version' => 'assisted-service-v1',
            'consented_at' => now(),
            'evidence_media_id' => $media->id,
        ];

        $record = AssistedCustomerService::query()->create($attributes);

        self::assertDatabaseHas('assisted_customer_services', ['id' => $record->id, ...$attributes]);

        try {
            AssistedCustomerService::query()->create([...$attributes, 'owner_id' => $operator->id]);
            self::fail('Owner and operator must not be the same user.');
        } catch (QueryException) {
            self::assertDatabaseCount('assisted_customer_services', 1);
        }

        $this->expectException(\LogicException::class);
        $record->delete();
    }
}
