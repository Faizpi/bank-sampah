<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Reports\Models\ReportExport;
use App\Domain\Reports\Services\ReportExportService;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ReportExportController extends Controller
{
    public function __invoke(ReportExport $export, ReportExportService $service): BinaryFileResponse
    {
        /** @var User|null $actor */
        $actor = auth()->user();
        abort_unless($actor instanceof User, 404);
        $media = $service->download($actor, $export);
        $path = Storage::disk((string) $media->disk)->path((string) $media->path);
        abort_unless(is_file($path), 404);

        return response()->download($path, (string) $export->filename, [
            'Content-Type' => (string) $media->mime_type,
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
