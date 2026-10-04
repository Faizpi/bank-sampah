<?php

declare(strict_types=1);

namespace App\Domain\Operations\Models;

use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $backup_pair_uuid
 * @property string|null $operator_key
 * @property string|null $request_payload_hash
 * @property BackupStatus $status
 * @property string $database_location_alias
 * @property string $media_location_alias
 * @property string $database_sha256
 * @property string $media_sha256
 * @property int $database_size_bytes
 * @property int $media_size_bytes
 * @property CarbonImmutable $retention_until
 * @property CarbonImmutable|null $restore_tested_at
 * @property BackupRestoreVerificationResult|null $restore_verification_result
 * @property string|null $restore_verification_evidence_reference
 */
final class BackupLog extends Model
{
    protected $guarded = ['id'];

    protected $hidden = ['operator_key', 'request_payload_hash', 'restore_verification_evidence_reference'];

    protected function casts(): array
    {
        return [
            'status' => BackupStatus::class,
            'database_size_bytes' => 'integer',
            'media_size_bytes' => 'integer',
            'retention_until' => 'immutable_datetime',
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'restore_tested_at' => 'immutable_datetime',
            'restore_verification_result' => BackupRestoreVerificationResult::class,
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function initiator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by');
    }
}
