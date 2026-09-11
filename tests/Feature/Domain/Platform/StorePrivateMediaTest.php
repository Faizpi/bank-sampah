<?php

declare(strict_types=1);

namespace Tests\Feature\Domain\Platform;

use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

final class StorePrivateMediaTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('allowedFileProvider')]
    public function test_it_stores_each_signature_verified_allowed_file_on_the_private_disk(
        string $name,
        string $contents,
        string $detectedMimeType,
        string $extension,
    ): void {
        Storage::fake('media_private');
        $uploader = User::factory()->create();

        $media = app(StorePrivateMedia::class)->handle($this->uploadedFile($name, $contents), $uploader);

        self::assertSame('media_private', $media->disk);
        self::assertSame($name, $media->original_name);
        self::assertSame($detectedMimeType, $media->mime_type);
        self::assertSame('private', $media->getRawOriginal('visibility'));
        self::assertSame($uploader->getKey(), $media->uploader_id);
        self::assertStringNotContainsString(pathinfo($name, PATHINFO_FILENAME), $media->path);
        self::assertMatchesRegularExpression(sprintf('/^[a-f0-9-]{36}\.%s$/', $extension), $media->path);
        self::assertSame(hash('sha256', $contents), $media->checksum);
        Storage::disk('media_private')->assertExists($media->path);
        self::assertDatabaseHas('media', ['id' => $media->getKey(), 'path' => $media->path]);
    }

    public function test_it_accepts_a_legitimate_png_when_the_client_mime_type_is_false(): void
    {
        Storage::fake('media_private');

        $media = app(StorePrivateMedia::class)->handle($this->uploadedFile('receipt.png', self::png(), 'text/plain'));

        self::assertSame('image/png', $media->mime_type);
        Storage::disk('media_private')->assertExists($media->path);
    }

    public function test_it_stores_a_valid_small_jpeg_directly_without_reencoding_it(): void
    {
        Storage::fake('media_private');
        $contents = self::jpeg();

        $media = app(StorePrivateMedia::class)->handlePhoto($this->uploadedFile('pickup.jpg', $contents, 'text/plain'));

        self::assertSame($contents, Storage::disk('media_private')->get($media->path));
        self::assertSame(strlen($contents), $media->size);
        self::assertSame(hash('sha256', $contents), $media->checksum);
        self::assertSame('image/jpeg', $media->mime_type);
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}\.jpg$/', $media->path);
    }

    public function test_it_normalizes_pickup_photos_to_private_jpeg_files_under_one_megabyte(): void
    {
        Storage::fake('media_private');

        $media = app(StorePrivateMedia::class)->handlePhoto(UploadedFile::fake()->image('pickup.png', 100, 100));
        $contents = Storage::disk('media_private')->get($media->path);

        self::assertSame('image/jpeg', $media->mime_type);
        self::assertSame('pickup.png', $media->original_name);
        self::assertMatchesRegularExpression('/^[a-f0-9-]{36}\.jpg$/', $media->path);
        self::assertLessThanOrEqual(1024 * 1024, $media->size);
        self::assertSame($media->size, strlen($contents));
        self::assertSame(hash('sha256', $contents), $media->checksum);
        self::assertStringStartsWith("\xFF\xD8\xFF", $contents);
    }

    public function test_an_overdimension_jpeg_uses_the_existing_normalization_path(): void
    {
        Storage::fake('media_private');
        $file = UploadedFile::fake()->image('wide.jpg', 2001, 1);
        $original = $file->getContent();

        $media = app(StorePrivateMedia::class)->handlePhoto($file);
        $stored = Storage::disk('media_private')->get($media->path);
        $dimensions = getimagesizefromstring($stored);

        self::assertNotSame($original, $stored);
        self::assertLessThanOrEqual(1024 * 1024, strlen($stored));
        self::assertIsArray($dimensions);
        self::assertLessThanOrEqual(2000, max($dimensions[0], $dimensions[1]));
    }

    public function test_no_gd_path_stores_a_qualifying_jpeg_without_normalization(): void
    {
        Storage::fake('media_private');
        $contents = self::jpeg();

        $media = app(StorePrivateMedia::class)->handlePhoto($this->uploadedFile('photo.jpg', $contents));
        $stored = Storage::disk('media_private')->get($media->path);

        self::assertSame($contents, $stored);
        self::assertSame(strlen($contents), $media->size);
        self::assertSame(hash('sha256', $contents), $media->checksum);
        self::assertSame('image/jpeg', $media->mime_type);
    }

    public function test_nonqualifying_photo_uses_gd_fallback_and_fails_cleanly_without_gd(): void
    {
        Storage::fake('media_private');
        $file = UploadedFile::fake()->image('wide.jpg', 2001, 1);

        $this->expectException(ValidationException::class);
        try {
            (new StorePrivateMedia(false))->handlePhoto($file);
        } finally {
            self::assertSame(0, Media::query()->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_jpeg_alias_is_accepted_and_stored_as_canonical_jpg(): void
    {
        Storage::fake('media_private');

        $media = app(StorePrivateMedia::class)->handlePhoto(UploadedFile::fake()->image('pickup.jpeg', 100, 100));

        self::assertStringEndsWith('.jpg', $media->path);
        self::assertSame('image/jpeg', $media->mime_type);
    }

    public function test_jpeg_extension_alias_is_normalized_to_canonical_jpg_output(): void
    {
        Storage::fake('media_private');

        $media = app(StorePrivateMedia::class)->handlePhoto(UploadedFile::fake()->image('pickup.jpeg', 100, 100));

        self::assertSame('pickup.jpeg', $media->original_name);
        self::assertStringEndsWith('.jpg', $media->path);
        self::assertSame('image/jpeg', $media->mime_type);
    }

    public function test_photo_fast_path_does_not_bypass_type_signature_image_or_embedded_content_checks(): void
    {
        Storage::fake('media_private');

        foreach ([
            $this->uploadedFile('photo.png', self::jpeg()),
            $this->uploadedFile('photo.jpg', self::png()),
            $this->uploadedFile('photo.jpg', "\xFF\xD8\xFFinvalid"),
            $this->uploadedFile('photo.jpg', self::jpeg().'<?php echo "unsafe"; ?>'),
        ] as $file) {
            try {
                app(StorePrivateMedia::class)->handlePhoto($file);
                self::fail('An invalid photo must be rejected before storage.');
            } catch (ValidationException) {
                // Expected: invalid input is rejected before direct storage.
            }
        }

        self::assertSame(0, Media::query()->count());
        Storage::disk('media_private')->assertDirectoryEmpty('/');
    }

    public function test_it_preserves_signature_verified_evidence_bytes_without_gd_normalization(): void
    {
        Storage::fake('media_private');

        $pngContents = self::png();
        $image = app(StorePrivateMedia::class)->handleEvidence($this->uploadedFile('evidence.png', $pngContents));
        $pdfContents = self::pdf();
        $pdf = app(StorePrivateMedia::class)->handleEvidence($this->uploadedFile('evidence.pdf', $pdfContents));

        self::assertSame('image/png', $image->mime_type);
        self::assertStringEndsWith('.png', $image->path);
        self::assertSame($pngContents, Storage::disk('media_private')->get($image->path));
        self::assertSame(hash('sha256', $pngContents), $image->checksum);
        self::assertSame('application/pdf', $pdf->mime_type);
        self::assertStringEndsWith('.pdf', $pdf->path);
        self::assertSame($pdfContents, Storage::disk('media_private')->get($pdf->path));
    }

    public function test_it_rejects_an_extension_mismatch_without_storing_metadata_or_files(): void
    {
        Storage::fake('media_private');

        $this->expectException(ValidationException::class);

        try {
            app(StorePrivateMedia::class)->handle($this->uploadedFile('receipt.jpg', self::png(), 'image/jpeg'));
        } finally {
            self::assertSame(0, Media::query()->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    public function test_it_rejects_oversize_prohibited_and_traversal_shaped_uploads_without_storing_files(): void
    {
        Storage::fake('media_private');

        foreach ([
            $this->uploadedFile('too-large.png', str_repeat('x', 5 * 1024 * 1024 + 1)),
            $this->uploadedFile('unsafe.svg', '<svg xmlns="http://www.w3.org/2000/svg"/>', 'image/svg+xml'),
            $this->uploadedFile('%2e%2e%2freceipt.png', self::png()),
            $this->uploadedFile('polyglot.png', self::png().'<?php echo "unsafe"; ?>'),
        ] as $file) {
            try {
                app(StorePrivateMedia::class)->handle($file);
                self::fail('The unsafe upload must be rejected.');
            } catch (ValidationException) {
                // Expected: every invalid upload is rejected before storage.
            }
        }

        self::assertSame(0, Media::query()->count());
        Storage::disk('media_private')->assertDirectoryEmpty('/');
    }

    public function test_it_removes_the_stored_file_when_a_real_metadata_constraint_failure_occurs(): void
    {
        Storage::fake('media_private');
        DB::unprepared(<<<'SQL'
            CREATE TRIGGER media_reject_upload_metadata_insert
            BEFORE INSERT ON media
            WHEN NEW.original_name = 'metadata-failure.png'
            BEGIN
                SELECT RAISE(ABORT, 'forced metadata failure');
            END;
            SQL);

        $this->expectException(QueryException::class);

        try {
            app(StorePrivateMedia::class)->handle($this->uploadedFile('metadata-failure.png', self::png()));
        } finally {
            self::assertSame(0, Media::query()->count());
            Storage::disk('media_private')->assertDirectoryEmpty('/');
        }
    }

    /** @return iterable<string, array{string, string, string, string}> */
    public static function allowedFileProvider(): iterable
    {
        yield 'JPEG' => ['receipt.jpg', self::jpeg(), 'image/jpeg', 'jpg'];
        yield 'PNG' => ['receipt.png', self::png(), 'image/png', 'png'];
        yield 'WebP' => ['receipt.webp', self::webp(), 'image/webp', 'webp'];
        yield 'PDF' => ['receipt.pdf', self::pdf(), 'application/pdf', 'pdf'];
    }

    private function uploadedFile(string $name, string $contents, string $clientMimeType = 'application/octet-stream'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'media-test-');
        self::assertIsString($path);
        file_put_contents($path, $contents);

        return new UploadedFile($path, $name, $clientMimeType, null, true);
    }

    private static function jpeg(): string
    {
        return base64_decode('/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAP//////////////////////////////////////////////////////////////////////////////////////2wBDAf//////////////////////////////////////////////////////////////////////////////////////wAARCAABAAEDASIAAhEBAxEB/8QAFQABAQAAAAAAAAAAAAAAAAAAAAX/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oADAMBAAIQAxAAAAH/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAEFAqf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAEDAQE/Aaf/xAAUEQEAAAAAAAAAAAAAAAAAAAAA/9oACAECAQE/Aaf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAY/Aqf/xAAUEAEAAAAAAAAAAAAAAAAAAAAA/9oACAEBAAE/IV//2gAMAwEAAgADAAAAEP/EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQMBAT8QH//EABQRAQAAAAAAAAAAAAAAAAAAABD/2gAIAQIBAT8QH//EABQQAQAAAAAAAAAAAAAAAAAAABD/2gAIAQEAAT8QH//Z', true) ?: throw new \LogicException('Invalid JPEG fixture.');
    }

    private static function png(): string
    {
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true) ?: throw new \LogicException('Invalid PNG fixture.');
    }

    private static function webp(): string
    {
        return base64_decode('UklGRiIAAABXRUJQVlA4IBYAAADQAwCdASoBAAEAAUAmJaQAA3AA/vuUAAA=', true) ?: throw new \LogicException('Invalid WebP fixture.');
    }

    private static function pdf(): string
    {
        return "%PDF-1.4\n1 0 obj\n<< /Type /Catalog /Pages 2 0 R >>\nendobj\n2 0 obj\n<< /Type /Pages /Count 0 >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
    }
}
