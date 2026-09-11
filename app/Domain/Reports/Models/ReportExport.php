<?php

declare(strict_types=1);

namespace App\Domain\Reports\Models;

use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Enums\ReportExportStatus;
use App\Domain\Reports\Enums\ReportFormat;
use App\Domain\Reports\Enums\ReportType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsTo as MediaRelation;
use LogicException;

/**
 * @property ReportExportStatus $status
 * @property ReportFormat $format
 * @property ReportType $report_type
 * @property string $sort
 * @property string $direction
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $completed_at
 * @property string|null $path
 * @property string|null $disk
 * @property string|null $filename
 * @property int|null $media_id
 * @property Media|null $media
 * @property array<string, mixed> $filters
 * @property list<string>|null $columns
 */
final class ReportExport extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'columns' => 'array',
            'format' => ReportFormat::class,
            'report_type' => ReportType::class,
            'status' => ReportExportStatus::class,
            'expires_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /** @return MediaRelation<Media, $this> */
    public function media(): MediaRelation
    {
        return $this->belongsTo(Media::class, 'media_id');
    }

    public function isAvailable(): bool
    {
        return $this->status === ReportExportStatus::Succeeded && $this->expires_at->isFuture() && $this->path !== null;
    }

    protected static function booted(): void
    {
        self::deleting(static function (): void {
            throw new LogicException('Report exports are retained by the retention process.');
        });
    }
}
