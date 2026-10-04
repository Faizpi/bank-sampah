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
        return base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVQIHWP4z8DwHwAFgAI/ScL7wQAAAABJRU5ErkJggg==', true) ?: throw new \LogicException('Invalid PNG fixture.');
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
