<?php

declare(strict_types=1);

namespace App\Domain\WasteMaster\Actions;

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Platform\Actions\StorePrivateMedia;
use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Models\WasteTypePhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final readonly class SaveWasteTypePhoto
{
    public function __construct(private StorePrivateMedia $images, private AuditLogger $audit) {}

    public function handle(User $actor, WasteType $type, UploadedFile $file): WasteTypePhoto
    {
        $path = null;
        try {
            return DB::transaction(function () use ($actor, $type, $file, &$path): WasteTypePhoto {
                $currentActor = User::query()->lockForUpdate()->findOrFail($actor->id);
                $currentType = WasteType::query()->lockForUpdate()->findOrFail($type->id);
                Gate::forUser($currentActor)->authorize('update', $currentType);
                try {
                    $contents = $this->images->sanitizedPhotoContents($file);
                } catch (ValidationException $exception) {
                    throw ValidationException::withMessages(['photo' => collect($exception->errors())->flatten()->first() ?? 'Foto tidak valid.']);
                }
                $uuid = (string) Str::uuid();
                $path = $uuid.'.jpg';
                if (! Storage::disk('waste_photos')->put($path, $contents)) {
                    throw new RuntimeException('Foto sampah gagal disimpan.');
                }
                $photo = WasteTypePhoto::query()->firstOrNew(['waste_type_id' => $currentType->id]);
                $old = $photo->exists ? ['photo_uuid' => $photo->uuid] : [];
                $photo->fill(['uuid' => $uuid, 'path' => $path, 'size' => strlen($contents), 'checksum' => hash('sha256', $contents), 'uploaded_by' => $currentActor->id])->save();
                $this->audit->record($currentActor, 'waste.catalog.photo.saved', $currentType, $old, ['photo_uuid' => $uuid], (string) Str::uuid());

                return $photo;
            });
        } catch (Throwable $exception) {
            if (is_string($path)) {
                Storage::disk('waste_photos')->delete($path);
            }
            throw $exception;
        }
    }
}
