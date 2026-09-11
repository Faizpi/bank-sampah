<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Platform;

use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class MediaFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_private_media_disk_is_local_and_outside_the_public_root(): void
    {
        $disk = config('filesystems.disks.media_private');

        self::assertIsArray($disk);
        self::assertSame('local', $disk['driver']);
        self::assertSame(storage_path('app/media'), $disk['root']);
        self::assertStringNotContainsString(public_path(), $disk['root']);
        self::assertArrayNotHasKey('url', $disk);
        self::assertArrayNotHasKey('visibility', $disk);
        self::assertNotContains($disk['root'], config('filesystems.links'));
    }

    public function test_media_schema_contains_canonical_metadata_without_blob_columns(): void
    {
        self::assertTrue(Schema::hasColumns('media', [
            'id', 'uuid', 'disk', 'path', 'original_name', 'mime_type', 'size',
            'checksum', 'visibility', 'uploader_id', 'attachable_type',
            'attachable_id', 'created_at', 'updated_at',
        ]));
        self::assertFalse(Schema::hasColumn('media', 'blob'));
        self::assertFalse(Schema::hasColumn('media', 'contents'));
    }

    public function test_media_declares_unique_and_polymorphic_composite_indexes(): void
    {
        $indexes = Schema::getIndexes('media');
        $indexByColumns = [];

        foreach ($indexes as $index) {
            $indexByColumns[implode(',', $index['columns'])] = $index;
        }

        self::assertTrue($indexByColumns['uuid']['unique']);
        self::assertTrue($indexByColumns['disk,path']['unique']);
        self::assertArrayHasKey('attachable_type,attachable_id', $indexByColumns);
        self::assertArrayNotHasKey('attachable_id,attachable_type', $indexByColumns);
    }

    public function test_media_rejects_duplicate_uuid(): void
    {
        $attributes = $this->mediaAttributes();
        DB::table('media')->insert($attributes);

        $this->expectException(QueryException::class);
        DB::table('media')->insert([...$attributes, 'path' => 'other/path']);
    }

    public function test_media_rejects_negative_size_on_sqlite(): void
    {
        $this->expectException(QueryException::class);
        DB::table('media')->insert([...$this->mediaAttributes(), 'size' => -1]);
    }

    public function test_media_rejects_non_private_visibility_at_database_boundary(): void
    {
        $this->expectException(QueryException::class);
        DB::table('media')->insert([...$this->mediaAttributes(), 'visibility' => 'public']);
    }

    public function test_media_migration_declares_portable_private_visibility_and_nonnegative_size_checks(): void
    {
        $migration = file_get_contents(database_path('migrations/2026_07_30_110000_create_media_table.php'));

        self::assertIsString($migration);
        self::assertStringContainsString("CHECK (visibility IN ('private'))", $migration);
        self::assertStringContainsString('CHECK (size >= 0)', $migration);
        self::assertStringContainsString("DB::getDriverName() === 'sqlite'", $migration);
        self::assertStringContainsString('CREATE TRIGGER media_visibility_private_insert', $migration);
        self::assertStringContainsString('CREATE TRIGGER media_size_nonnegative_insert', $migration);
    }

    public function test_media_rejects_duplicate_disk_and_path(): void
    {
        $attributes = $this->mediaAttributes();
        DB::table('media')->insert($attributes);

        $this->expectException(QueryException::class);
        DB::table('media')->insert([...$attributes, 'uuid' => '018f4ca4-2e67-7c16-a455-8f610f6f5643']);
    }

    public function test_deleting_uploader_preserves_media_and_sets_uploader_to_null(): void
    {
        $uploader = User::factory()->create();
        $media = new Media;
        $media->fill([...$this->mediaAttributes(), 'uploader_id' => $uploader->getKey()]);
        $media->save();

        $uploader->forceDelete();

        self::assertDatabaseHas('media', ['id' => $media->getKey(), 'uploader_id' => null]);
    }

    public function test_media_model_casts_size_exposes_relationships_and_hides_storage_secrets(): void
    {
        $uploader = User::factory()->create();
        $media = new Media;
        $media->fill([...$this->mediaAttributes(), 'size' => '42', 'uploader_id' => $uploader->getKey()]);
        $media->save();

        self::assertSame(42, $media->size);
        self::assertSame(MediaVisibility::Private, $media->visibility);
        self::assertTrue($media->uploader->is($uploader));
        self::assertNull($media->attachable);
        self::assertArrayNotHasKey('path', $media->toArray());
        self::assertArrayNotHasKey('checksum', $media->toArray());
    }

    /** @return array<string, int|string|null> */
    private function mediaAttributes(): array
    {
        return [
            'uuid' => '018f4ca4-2e67-7c16-a455-8f610f6f5642',
            'disk' => 'media_private',
            'path' => '01J2/example.bin',
            'original_name' => 'example.bin',
            'mime_type' => 'application/octet-stream',
            'size' => 42,
            'checksum' => str_repeat('a', 64),
            'visibility' => 'private',
            'uploader_id' => null,
            'attachable_type' => null,
            'attachable_id' => null,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ];
    }
}
