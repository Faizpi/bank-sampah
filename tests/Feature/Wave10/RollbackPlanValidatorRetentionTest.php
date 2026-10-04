<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\RollbackPlan;
use App\Domain\Operations\Services\RollbackPlanValidator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('accepts a persisted retained backup pair with passed isolated restore verification', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup())
    );

    expect($result->passes())->toBeTrue();
});

it('reloads the persisted backup by key instead of trusting stale metadata', function (): void {
    $backup = rollbackRetentionBackup();
    $backup->forceFill(['status' => BackupStatus::Failed]);

    $result = app(RollbackPlanValidator::class)->validate(rollbackRetentionPlan($backup));

    expect($result->passes())->toBeTrue();
});

it('rejects a backup pair with overdue retention', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup(['retention_until' => CarbonImmutable::now()->subSecond()]))
    );

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
});

it('rejects missing retention metadata before any persisted row can qualify', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(rollbackRetentionPlan(new BackupLog));

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
});

it('rejects a backup pair with incomplete or failed restore verification', function (array $attributes): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup($attributes))
    );

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
})->with([
    [['restore_tested_at' => null]],
    [['restore_verification_target_alias' => null]],
    [['restore_verification_evidence_reference' => null]],
    [['restore_verification_evidence_reference' => 'invalid-evidence-reference']],
    [['restore_verification_result' => BackupRestoreVerificationResult::Failed]],
    [['status' => BackupStatus::Processing, 'finished_at' => null]],
    [['finished_at' => null]],
]);

it('rejects invalid and non-isolated artifact or verification aliases', function (array $attributes): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup($attributes))
    );

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
})->with([
    [['database_location_alias' => 'db']],
    [['media_location_alias' => 'media alias']],
    [['media_location_alias' => 'backup-db-20260803']],
    [['restore_verification_target_alias' => 'restore-production']],
    [['restore_verification_target_alias' => 'verify-production-lane']],
]);

it('rejects bad and zero artifact checksums or sizes', function (array $attributes): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup($attributes))
    );

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
})->with([
    [['database_sha256' => 'not-a-checksum']],
    [['media_sha256' => str_repeat('A', 64)]],
    [['database_size_bytes' => 0]],
    [['media_size_bytes' => 0]],
]);

it('accepts equal valid artifact checksums and sizes', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup([
            'media_sha256' => str_repeat('a', 64),
            'media_size_bytes' => 1200,
        ]))
    );

    expect($result->passes())->toBeTrue();
});

it('rejects backup timestamps outside the completed verification window', function (array $attributes): void {
    $result = app(RollbackPlanValidator::class)->validate(
        rollbackRetentionPlan(rollbackRetentionBackup($attributes))
    );

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
})->with([
    [['started_at' => CarbonImmutable::parse('2099-01-01T00:00:00Z')]],
    [['finished_at' => CarbonImmutable::parse('2099-01-01T00:00:00Z')]],
    [['restore_tested_at' => CarbonImmutable::parse('2099-01-01T00:00:00Z')]],
    [['finished_at' => CarbonImmutable::now()->subMinutes(2)]],
    [['restore_tested_at' => CarbonImmutable::now()->subMinutes(2)]],
]);

it('rejects a backup pair whose persisted row is missing', function (): void {
    $backup = new BackupLog;
    $backup->setAttribute('id', 999999);

    $result = app(RollbackPlanValidator::class)->validate(rollbackRetentionPlan($backup));

    expect($result->passes())->toBeFalse()
        ->and($result->issueCodes())->toContain('verified_database_media_backup_required');
});

it('keeps the code-compatible no-backup rollback path valid', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(new RollbackPlan(
        schemaCompatible: true,
        requiresDatabaseMediaRestore: false,
        automaticMigrationRollbackRequested: false,
        verifiedBackup: null,
    ));

    expect($result->passes())->toBeTrue();
});

/** @param array<string, mixed> $overrides */
function rollbackRetentionBackup(array $overrides = []): BackupLog
{
    $now = CarbonImmutable::now();

    return BackupLog::query()->create(array_replace([
        'backup_pair_uuid' => (string) Str::uuid(),
        'initiated_by' => User::factory()->create()->id,
        'status' => BackupStatus::Succeeded,
        'database_location_alias' => 'backup-db-20260803',
        'media_location_alias' => 'backup-media-20260803',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
        'retention_until' => $now->addHour(),
        'started_at' => $now->subMinute(),
        'finished_at' => $now,
        'restore_tested_at' => $now,
        'restore_verification_result' => BackupRestoreVerificationResult::Passed,
        'restore_verification_target_alias' => 'verify-20260803-lane-d',
        'restore_verification_evidence_reference' => rollbackEvidenceReference(),
    ], $overrides));
}

function rollbackEvidenceReference(): string
{
    return str_repeat('e', 43);
}

function rollbackRetentionPlan(?BackupLog $backup): RollbackPlan
{
    return new RollbackPlan(
        schemaCompatible: false,
        requiresDatabaseMediaRestore: true,
        automaticMigrationRollbackRequested: false,
        verifiedBackup: $backup,
    );
}
