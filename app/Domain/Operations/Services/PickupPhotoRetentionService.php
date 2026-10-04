<?php

declare(strict_types=1);

namespace App\Domain\Operations\Services;

use App\Authorization\PermissionChecker;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

final readonly class PickupPhotoRetentionService
{
    private const DISK = 'media_private';

    private const TRASH_DIRECTORY = '.retention-trash';

    public function __construct(
        private PermissionChecker $permissions,
        private AuditLogger $auditLogger,
    ) {}

    public function preview(User $actor, string $before): PickupPhotoRetentionPreview
    {
        $this->authorize($actor);
        $before = $this->validatedBefore($before);
        $query = $this->candidateQuery($before);
        $batchLimit = $this->batchLimit();
        $disk = Storage::disk(self::DISK);
        $items = [];
        $missingFiles = 0;

        foreach ((clone $query)->limit($batchLimit)->get() as $media) {
            $pathIsSafe = $this->pathIsSafe($media->path);
            $fileExists = $pathIsSafe && $disk->exists($media->path);
            if (! $fileExists) {
                $missingFiles++;
            }

            $items[] = [
                'id' => (int) $media->getKey(),
                'pickup_number' => (string) $media->getAttribute('pickup_request_number'),
                'pickup_status' => (string) $media->getAttribute('pickup_status'),
                'original_name' => $media->original_name,
                'size' => (int) $media->size,
                'created_at' => $media->created_at?->timezone('Asia/Jakarta')->format('d M Y H:i') ?? '-',
                'file_exists' => $fileExists,
            ];
        }

        return new PickupPhotoRetentionPreview(
            before: $before,
            deletableCount: (clone $query)->count(),
            deletableBytes: (int) (clone $query)->sum('media.size'),
            batchCount: count($items),
            batchMissingFileCount: $missingFiles,
            batchLimit: $batchLimit,
            items: $items,
        );
    }

    public function execute(User $actor, string $before, string $correlationId): PickupPhotoRetentionResult
    {
        $this->authorize($actor);
        $before = $this->validatedBefore($before);
        $disk = Storage::disk(self::DISK);
        $runUuid = (string) Str::uuid();
        $trashRoot = self::TRASH_DIRECTORY.'/'.$runUuid;
        $movedFiles = [];

        try {
            /** @var PickupPhotoRetentionResult $result */
            $result = DB::transaction(function () use ($actor, $before, $correlationId, $disk, $runUuid, $trashRoot, &$movedFiles): PickupPhotoRetentionResult {
                $deletedCount = 0;
                $deletedBytes = 0;
                $missingFiles = 0;
                $candidates = $this->candidateQuery($before)
                    ->limit($this->batchLimit())
                    ->lockForUpdate()
                    ->get();

                foreach ($candidates as $media) {
                    if (! $this->pathIsSafe($media->path)) {
                        throw new RuntimeException('Retensi dihentikan karena menemukan path media yang tidak aman.');
                    }

                    if ($disk->exists($media->path)) {
                        $trashPath = $trashRoot.'/'.$media->path;
                        $this->moveOrFail($disk, $media->path, $trashPath);
                        $movedFiles[$media->path] = $trashPath;
                    } else {
                        $missingFiles++;
                    }

                    $deletedBytes += (int) $media->size;
                    $media->deleteOrFail();
                    $deletedCount++;
                }

                $this->auditLogger->record(
                    $actor,
                    'media.pickup_photo_retention.executed',
                    $actor,
                    [],
                    [
                        'run_uuid' => $runUuid,
                        'before' => $before,
                        'deleted_count' => $deletedCount,
                        'deleted_bytes' => $deletedBytes,
                        'missing_file_count' => $missingFiles,
                    ],
                    $correlationId,
                );

                return new PickupPhotoRetentionResult($deletedCount, $deletedBytes, $missingFiles);
            });
        } catch (Throwable $exception) {
            $this->restoreMovedFiles($disk, $movedFiles);
            $disk->deleteDirectory($trashRoot);

            throw $exception;
        }

        if (! $disk->deleteDirectory($trashRoot)) {
            Log::warning('Direktori karantina retensi media belum dapat dibersihkan.', [
                'run_uuid' => $runUuid,
                'trash_root' => $trashRoot,
            ]);
        }

        return $result;
    }

    /** @return Builder<Media> */
    private function candidateQuery(string $before): Builder
    {
        $pickupMorphType = (new PickupRequest)->getMorphClass();

        return Media::query()
            ->select([
                'media.*',
                'pickup_requests.request_number as pickup_request_number',
                'pickup_requests.status as pickup_status',
            ])
            ->join('pickup_requests', function (JoinClause $join) use ($pickupMorphType): void {
                $join->on('pickup_requests.id', '=', 'media.attachable_id')
                    ->where('media.attachable_type', '=', $pickupMorphType);
            })
            ->where('media.disk', self::DISK)
            ->where('media.visibility', 'private')
            ->where('media.mime_type', 'like', 'image/%')
            ->whereIn('pickup_requests.status', [
                PickupStatus::Completed->value,
                PickupStatus::Rejected->value,
                PickupStatus::Cancelled->value,
            ])
            ->where('media.created_at', '<', $before.' 00:00:00')
            ->orderBy('media.id');
    }

    private function authorize(User $actor): void
    {
        if (! $this->permissions->allows($actor, 'media.retention.execute')) {
            throw new AuthorizationException('Anda tidak memiliki akses retensi foto penjemputan.');
        }
    }

    private function validatedBefore(string $before): string
    {
        $date = CarbonImmutable::createFromFormat('!Y-m-d', $before, 'Asia/Jakarta');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $before) !== 1 || $date === null || $date->format('Y-m-d') !== $before) {
            throw ValidationException::withMessages(['mediaRetentionBefore' => 'Batas tanggal retensi foto tidak valid.']);
        }

        $minimumAgeDays = $this->minimumAgeDays();
        $latestCutoff = CarbonImmutable::now('Asia/Jakarta')->subDays($minimumAgeDays)->startOfDay();
        if ($date->greaterThan($latestCutoff)) {
            throw ValidationException::withMessages([
                'mediaRetentionBefore' => "Foto hanya dapat dihapus setelah berusia minimal {$minimumAgeDays} hari.",
            ]);
        }

        return $before;
    }

    private function minimumAgeDays(): int
    {
        return max(30, (int) config('operations.retention.pickup_photo_minimum_age_days', 30));
    }

    private function batchLimit(): int
    {
        return min(500, max(1, (int) config('operations.retention.pickup_photo_batch_size', 100)));
    }

    private function pathIsSafe(string $path): bool
    {
        if ($path === '' || str_contains($path, "\0")) {
            return false;
        }

        $normalized = str_replace('\\', '/', $path);

        return ! str_starts_with($normalized, '/')
            && ! preg_match('/^[A-Za-z]:\//', $normalized)
            && ! in_array('..', explode('/', $normalized), true);
    }

    private function moveOrFail(FilesystemAdapter $disk, string $from, string $to): void
    {
        $directory = dirname($to);
        if ($directory !== '.') {
            $disk->makeDirectory($directory);
        }

        if (! $disk->move($from, $to)) {
            throw new RuntimeException('Satu foto gagal dipindahkan ke karantina retensi. Tidak ada data yang dihapus.');
        }
    }

    /** @param array<string, string> $movedFiles */
    private function restoreMovedFiles(FilesystemAdapter $disk, array $movedFiles): void
    {
        foreach (array_reverse($movedFiles, true) as $originalPath => $trashPath) {
            try {
                if ($disk->exists($trashPath) && ! $disk->exists($originalPath)) {
                    $this->moveOrFail($disk, $trashPath, $originalPath);
                }
            } catch (Throwable $exception) {
                Log::critical('Pemulihan foto dari karantina retensi gagal.', [
                    'original_path' => $originalPath,
                    'trash_path' => $trashPath,
                    'exception' => $exception::class,
                ]);
            }
        }
    }
}
