<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Platform\Models\Media;
use App\Domain\Withdrawals\Services\WithdrawalService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class WithdrawalProofController extends Controller
{
    public function __invoke(Media $media, WithdrawalService $service): BinaryFileResponse
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User && $service->canDownloadProof($actor, $media), 404);
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
