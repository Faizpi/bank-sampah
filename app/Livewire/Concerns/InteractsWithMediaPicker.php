<?php

declare(strict_types=1);

namespace App\Livewire\Concerns;

use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

trait InteractsWithMediaPicker
{
    protected function clearMediaPickerUpload(string $property): void
    {
        $upload = $this->{$property} ?? null;
        if ($upload instanceof TemporaryUploadedFile) {
            $upload->delete();
        }

        $this->{$property} = null;
        $this->resetErrorBag($property);
    }

    /**
     * @param  array<int, string>  $rules
     * @param  array<string, string>  $messages
     * @return list<array{name: string, size: int, mimeType: string, previewUrl: string}>
     */
    protected function confirmMediaPickerUpload(string $property, array $rules, array $messages = []): array
    {
        $upload = $this->{$property} ?? null;
        if (! $upload instanceof UploadedFile) {
            return [];
        }

        $this->validate([$property => $rules], $messages);
        $this->resetErrorBag($property);

        return [self::mediaPickerMetadata($upload)];
    }

    /** @return array{name: string, size: int, mimeType: string, previewUrl: string} */
    protected static function mediaPickerMetadata(UploadedFile $upload): array
    {
        return [
            'name' => $upload->getClientOriginalName(),
            'size' => (int) $upload->getSize(),
            'mimeType' => (string) ($upload->getMimeType() ?? ''),
            'previewUrl' => $upload instanceof TemporaryUploadedFile ? $upload->temporaryUrl() : '',
        ];
    }
}
