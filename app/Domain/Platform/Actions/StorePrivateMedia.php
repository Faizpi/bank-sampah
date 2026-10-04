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

    public function __construct(private readonly ?bool $normalizationAvailable = null) {}

    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_PHOTO_BYTES = 1 * 1024 * 1024;

    private const PHOTO_MAX_DIMENSION = 2000;

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

        return $this->store($file, $contents, $mimeType, $extension, $uploader);
    }

    public function handlePhoto(UploadedFile $file, ?User $uploader = null): Media
    {
        $this->validate($file, true);

        $contents = $file->getContent();
        $mimeType = $this->detectedMimeType($contents);
        $dimensions = @getimagesizefromstring($contents);

        if ($mimeType === 'image/jpeg'
            && strlen($contents) <= self::MAX_PHOTO_BYTES
            && is_array($dimensions)
            && $dimensions[2] === IMAGETYPE_JPEG
            && $dimensions[0] <= self::PHOTO_MAX_DIMENSION
            && $dimensions[1] <= self::PHOTO_MAX_DIMENSION) {
            return $this->store($file, $contents, 'image/jpeg', 'jpg', $uploader);
        }

        return $this->store($file, $this->normalizePhoto($contents), 'image/jpeg', 'jpg', $uploader);
    }

    public function handleEvidence(UploadedFile $file, ?User $uploader = null): Media
    {
        $this->validate($file);

        $contents = $file->getContent();
        $mimeType = $this->detectedMimeType($contents);

        return $this->store($file, $contents, $mimeType, self::ALLOWED_TYPES[$mimeType]['extension'], $uploader);
    }

    private function store(UploadedFile $file, string $contents, string $mimeType, string $extension, ?User $uploader): Media
    {
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
                'size' => strlen($contents),
                'checksum' => hash('sha256', $contents),
                'visibility' => MediaVisibility::Private,
                'uploader_id' => $uploader?->getKey(),
            ]);
        } catch (Throwable $exception) {
            $disk->delete($path);

            throw $exception;
        }
    }

    private function validate(UploadedFile $file, bool $photoOnly = false): void
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

        $allowedTypes = $photoOnly
            ? array_filter(self::ALLOWED_TYPES, static fn (string $type): bool => str_starts_with($type, 'image/') && $type !== 'image/webp', ARRAY_FILTER_USE_KEY)
            : self::ALLOWED_TYPES;

        $validExtension = $mimeType === 'image/jpeg'
            ? in_array($extension, ['jpg', 'jpeg'], true)
            : $extension === ($allowedTypes[$mimeType]['extension'] ?? null);

        if (! isset($allowedTypes[$mimeType]) || ! $validExtension || ! $this->hasExpectedSignature($contents, $mimeType) || $this->hasProhibitedEmbeddedContent($contents)) {
            throw ValidationException::withMessages(['file' => 'The uploaded file type is not allowed.']);
        }

        if (str_starts_with($mimeType, 'image/') && @getimagesizefromstring($contents) === false) {
            throw ValidationException::withMessages(['file' => 'The uploaded image is invalid.']);
        }
    }

    private function normalizePhoto(string $contents): string
    {
        if ($this->normalizationAvailable === false || ! function_exists('imagecreatefromstring') || ! function_exists('imagejpeg')) {
            throw ValidationException::withMessages(['file' => 'Kompresi foto membutuhkan ekstensi GD PHP.']);
        }

        $source = @imagecreatefromstring($contents);
        if ($source === false) {
            throw ValidationException::withMessages(['file' => 'Foto tidak dapat diproses.']);
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $scale = min(1, self::PHOTO_MAX_DIMENSION / max($sourceWidth, $sourceHeight));
        $baseWidth = max(1, (int) round($sourceWidth * $scale));
        $baseHeight = max(1, (int) round($sourceHeight * $scale));

        try {
            for ($dimensionScale = 1.0; $dimensionScale >= 0.25; $dimensionScale *= 0.85) {
                $width = max(1, (int) round($baseWidth * $dimensionScale));
                $height = max(1, (int) round($baseHeight * $dimensionScale));
                $canvas = imagecreatetruecolor($width, $height);

                if ($canvas === false) {
                    continue;
                }

                imagealphablending($canvas, true);
                imagesavealpha($canvas, false);
                imagefilledrectangle($canvas, 0, 0, $width, $height, 0xFFFFFF);
                imagecopyresampled($canvas, $source, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight);

                foreach ([85, 75, 65, 55, 45, 35] as $quality) {
                    ob_start();
                    imagejpeg($canvas, null, $quality);
                    $encoded = ob_get_clean();

                    if (is_string($encoded) && strlen($encoded) <= self::MAX_PHOTO_BYTES) {
                        imagedestroy($canvas);

                        return $encoded;
                    }
                }

                imagedestroy($canvas);
            }
        } finally {
            imagedestroy($source);
        }

        throw ValidationException::withMessages(['file' => 'Foto terlalu kompleks untuk dikompres sampai 1 MB.']);
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
