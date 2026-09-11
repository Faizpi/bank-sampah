<?php

declare(strict_types=1);

namespace Tests\Feature\Operations;

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Operations\Services\PickupPhotoRetentionService;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class PickupPhotoRetentionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('media_private');
        $dusun = Dusun::query()->create(['code' => 'D-RET', 'name' => 'Dusun Retensi', 'is_active' => true]);
        Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'RW-RET', 'name' => 'RW Retensi', 'is_active' => true]);
        config()->set('operations.retention.pickup_photo_minimum_age_days', 30);
        config()->set('operations.retention.pickup_photo_batch_size', 100);
    }

    public function test_preview_and_execute_only_remove_old_terminal_pickup_photos(): void
    {
        $actor = $this->userWithMediaRetentionPermission();
        $oldTerminal = PickupRequest::factory()->create(['status' => PickupStatus::Completed]);
        $oldCancelled = PickupRequest::factory()->create(['status' => PickupStatus::Cancelled]);
        $active = PickupRequest::factory()->create(['status' => PickupStatus::PendingReview]);
        $recentTerminal = PickupRequest::factory()->create(['status' => PickupStatus::Rejected]);
        $oldDate = now()->subDays(90);
        $cutoff = now()->subDays(60)->toDateString();

        $eligible = $this->media($oldTerminal, 'eligible.jpg', 'image/jpeg', $oldDate, true, 4096);
        $missing = $this->media($oldCancelled, 'missing.jpg', 'image/jpeg', $oldDate, false, 2048);
        $activePhoto = $this->media($active, 'active.jpg', 'image/jpeg', $oldDate, true);
        $recentPhoto = $this->media($recentTerminal, 'recent.jpg', 'image/jpeg', now()->subDays(10), true);
        $document = $this->media($oldTerminal, 'document.pdf', 'application/pdf', $oldDate, true);
        $otherMedia = $this->standaloneMedia($actor, $oldDate);

        $service = app(PickupPhotoRetentionService::class);
        $preview = $service->preview($actor, $cutoff);

        self::assertSame(2, $preview->deletableCount);
        self::assertSame(6144, $preview->deletableBytes);
        self::assertSame(2, $preview->batchCount);
        self::assertSame(1, $preview->batchMissingFileCount);
        self::assertSame([$eligible->id, $missing->id], array_column($preview->items, 'id'));

        $result = $service->execute($actor, $cutoff, (string) Str::uuid());

        self::assertSame(2, $result->deletedCount);
        self::assertSame(6144, $result->deletedBytes);
        self::assertSame(1, $result->missingFileCount);
        self::assertFalse(Media::query()->whereKey($eligible->id)->exists());
        self::assertFalse(Media::query()->whereKey($missing->id)->exists());
        Storage::disk('media_private')->assertMissing($eligible->path);
        self::assertTrue(Media::query()->whereKey($activePhoto->id)->exists());
        self::assertTrue(Media::query()->whereKey($recentPhoto->id)->exists());
        self::assertTrue(Media::query()->whereKey($document->id)->exists());
        self::assertTrue(Media::query()->whereKey($otherMedia->id)->exists());
        Storage::disk('media_private')->assertExists($activePhoto->path);
        Storage::disk('media_private')->assertExists($recentPhoto->path);
        Storage::disk('media_private')->assertExists($document->path);
        Storage::disk('media_private')->assertExists($otherMedia->path);
        self::assertSame(1, AuditLog::query()->where('action', 'media.pickup_photo_retention.executed')->count());
        self::assertSame([], Storage::disk('media_private')->allFiles('.retention-trash'));
    }

    public function test_retention_requires_its_permission_and_a_cutoff_at_least_thirty_days_old(): void
    {
        $service = app(PickupPhotoRetentionService::class);

        $this->expectException(AuthorizationException::class);
        $service->preview(User::factory()->create(), now()->subDays(60)->toDateString());
    }

    public function test_retention_rejects_a_cutoff_that_is_too_recent(): void
    {
        $actor = $this->userWithMediaRetentionPermission();

        try {
            app(PickupPhotoRetentionService::class)->preview($actor, now()->subDays(10)->toDateString());
            self::fail('A recent retention cutoff must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('mediaRetentionBefore', $exception->errors());
        }
    }

    public function test_failed_database_delete_restores_quarantined_photo_and_rolls_back_audit(): void
    {
        $actor = $this->userWithMediaRetentionPermission();
        $pickup = PickupRequest::factory()->create(['status' => PickupStatus::Completed]);
        $media = $this->media($pickup, 'restore-me.jpg', 'image/jpeg', now()->subDays(90), true);
        $cutoff = now()->subDays(60)->toDateString();
        DB::unprepared("CREATE TRIGGER prevent_media_retention_delete BEFORE DELETE ON media BEGIN SELECT RAISE(ABORT, 'Simulated media delete failure.'); END");

        try {
            app(PickupPhotoRetentionService::class)->execute($actor, $cutoff, (string) Str::uuid());
            self::fail('The simulated database failure must abort retention.');
        } catch (QueryException) {
            self::assertTrue(Media::query()->whereKey($media->id)->exists());
            Storage::disk('media_private')->assertExists($media->path);
            self::assertSame(0, AuditLog::query()->where('action', 'media.pickup_photo_retention.executed')->count());
            self::assertSame([], Storage::disk('media_private')->allFiles('.retention-trash'));
        } finally {
            DB::unprepared('DROP TRIGGER IF EXISTS prevent_media_retention_delete');
        }
    }

    private function userWithMediaRetentionPermission(): User
    {
        $permission = Permission::query()->where('name', 'media.retention.execute')->sole();
        $role = Role::query()->create(['name' => 'media-retention-'.Str::lower(Str::random(8))]);
        $role->permissions()->attach($permission);
        $actor = User::factory()->create();
        $actor->roles()->attach($role);

        return $actor->fresh();
    }

    private function media(
        PickupRequest $pickup,
        string $name,
        string $mimeType,
        \DateTimeInterface $createdAt,
        bool $storeFile,
        int $size = 1024,
    ): Media {
        $path = (string) Str::uuid().'.'.pathinfo($name, PATHINFO_EXTENSION);
        if ($storeFile) {
            Storage::disk('media_private')->put($path, str_repeat('x', min($size, 64)));
        }

        $media = $pickup->media()->create($this->mediaAttributes($path, $name, $mimeType, $size));
        $media->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $media->fresh();
    }

    private function standaloneMedia(User $actor, \DateTimeInterface $createdAt): Media
    {
        $path = (string) Str::uuid().'.jpg';
        Storage::disk('media_private')->put($path, 'other');
        $media = Media::query()->create([
            ...$this->mediaAttributes($path, 'other.jpg', 'image/jpeg', 5),
            'attachable_type' => User::class,
            'attachable_id' => $actor->id,
        ]);
        $media->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->saveQuietly();

        return $media->fresh();
    }

    /** @return array<string, int|string|null> */
    private function mediaAttributes(string $path, string $name, string $mimeType, int $size): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'disk' => 'media_private',
            'path' => $path,
            'original_name' => $name,
            'mime_type' => $mimeType,
            'size' => $size,
            'checksum' => hash('sha256', $path),
            'visibility' => 'private',
            'uploader_id' => null,
        ];
    }
}
