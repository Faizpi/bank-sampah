<?php

declare(strict_types=1);

namespace App\Domain\Platform\Actions;

use App\Domain\Platform\Enums\MediaVisibility;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final class StorePrivateMedia
{
    private const DISK = 'media_private';

    private const MAX_BYTES = 5 * 1024 * 1024;

    /** @var array<string, array{extension: string, signature: string}> */
    private const ALLOWED_TYPES = [
        'image/jpeg' => ['extension' => 'jpg', 'signature' => "\xFF\xD8\xFF"],
        'image/png' => ['extension' => 'png', 'signature' => "\x89PNG\r\n\x1A\n"],
        'image/webp' => ['extension' => 'webp', 'signature' => 'RIFF'],
        'application/pdf' => ['extension' => 'pdf', 'signature' => '%PDF-'],
    ];

    public function handle(UploadedFile $file, ?User $uploader = null): Media
    {
        $this->validate($file);

        $contents = $file->getContent();
        $mimeType = $this->detectedMimeType($contents);
        $extension = self::ALLOWED_TYPES[$mimeType]['extension'];
        $path = sprintf('%s.%s', (string) Str::uuid(), $extension);
        $disk = Storage::disk(self::DISK);

        if ($disk->put($path, $contents) === false) {
            throw new RuntimeException('Private media storage failed.');
        }

        try {
            return Media::query()->create([
                'uuid' => (string) Str::uuid(),
                'disk' => self::DISK,
                'path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => $mimeType,
                'size' => $file->getSize(),
                'checksum' => hash('sha256', $contents),
                'visibility' => MediaVisibility::Private,
                'uploader_id' => $uploader?->getKey(),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

    private function validate(UploadedFile $file): void
    {
        $name = $file->getClientOriginalName();
        $size = $file->getSize();

        $decodedName = rawurldecode($name);

        if (! $file->isValid() || $name === '' || str_contains($decodedName, '..') || strpbrk($decodedName, '/\\') !== false || $size === false || $size < 1 || $size > self::MAX_BYTES) {
            throw ValidationException::withMessages(['file' => 'The uploaded file is invalid.']);
        }

        $contents = $file->getContent();
        $mimeType = $this->detectedMimeType($contents);
        $extension = strtolower((string) pathinfo($name, PATHINFO_EXTENSION));

        if (! isset(self::ALLOWED_TYPES[$mimeType]) || $extension !== self::ALLOWED_TYPES[$mimeType]['extension'] || ! $this->hasExpectedSignature($contents, $mimeType) || $this->hasProhibitedEmbeddedContent($contents)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file type is not allowed.']);
        }

        if (str_starts_with($mimeType, 'image/') && @getimagesizefromstring($contents) === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded image is invalid.']);
        }
    }

    private function detectedMimeType(string $contents): string
    {
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = $finfo === false ? false : finfo_buffer($finfo, $contents);

        if ($finfo !== false) {
            finfo_close($finfo);
        }

        if (! is_string($mimeType)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file type cannot be determined.']);
        }

        return $mimeType;
    }

    private function hasProhibitedEmbeddedContent(string $contents): bool
    {
        return preg_match('/<\?(?:php|=)|<script\b|<svg\b|<html\b/i', $contents) === 1;
    }

    private function hasExpectedSignature(string $contents, string $mimeType): bool
    {
        $signature = self::ALLOWED_TYPES[$mimeType]['signature'];

        if (! str_starts_with($contents, $signature)) {
            return false;
        }

        return $mimeType !== 'image/webp' || substr($contents, 8, 4) === 'WEBP';
    }
}
