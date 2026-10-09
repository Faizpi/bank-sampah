<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\WasteMaster\Models\WasteType;
use App\Domain\WasteMaster\Queries\PublicWasteCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WastePhotoController
{
    public function __invoke(Request $request, WasteType $wasteType, string $photoUuid, PublicWasteCatalog $catalog): BinaryFileResponse
    {
        $canPreview = $request->user() !== null && Gate::forUser($request->user())->allows('update', $wasteType);
        abort_unless($canPreview || $catalog->visibleTypes()->whereKey($wasteType->id)->exists(), 404);
        $photo = $wasteType->publicPhoto;
        abort_unless($photo !== null && hash_equals($photo->uuid, $photoUuid)
            && preg_match('/^[a-f0-9-]{36}\.jpg$/', $photo->path) === 1
            && Storage::disk('waste_photos')->exists($photo->path), 404);

        $response = response()->file(Storage::disk('waste_photos')->path($photo->path), [
            'Content-Type' => 'image/jpeg', 'Content-Disposition' => 'inline',
            'Cache-Control' => 'no-store, max-age=0',
        ]);
        $response->setPrivate();

        return $response;
    }
}
