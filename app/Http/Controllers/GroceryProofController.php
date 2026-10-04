<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Groceries\Models\GroceryRedemption;
use App\Domain\Groceries\Services\GroceryService;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class GroceryProofController extends Controller
{
    public function __invoke(Media $media, GroceryService $service): BinaryFileResponse
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $service->canDownloadProof($actor, $media), 404);
        abort_unless($media->attachable_type === GroceryRedemption::class && $media->getRawOriginal('visibility') === 'private', 404);
        $path = Storage::disk((string) $media->disk)->path((string) $media->path);
        abort_unless(is_file($path), 404);

        return response()->file($path, [
            'Content-Type' => (string) $media->mime_type,
            'Content-Disposition' => 'attachment; filename="'.addcslashes((string) $media->original_name, '"\\').'"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
