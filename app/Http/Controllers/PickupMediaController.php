<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Pickups\Services\PickupService;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class PickupMediaController extends Controller
{
    public function __invoke(Media $media, PickupService $service): BinaryFileResponse
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $service->canDownloadMedia($actor, $media), 404);
        abort_unless($media->getRawOriginal('visibility') === 'private' && $media->attachable_type === PickupRequest::class, 404);

        $path = Storage::disk((string) $media->disk)->path((string) $media->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => (string) $media->mime_type,
            'Content-Disposition' => 'inline; filename="'.addcslashes((string) $media->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
