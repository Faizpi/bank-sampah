<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Exceptions\BackupRequestConflict;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Domain\Operations\Services\DeploymentTopologyValidator;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Domain\Operations\Services\RollbackPlan;
use App\Domain\Operations\Services\RollbackPlanValidator;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('records a checksummed database and media backup pair through valid statuses and retention', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);

    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));
    $service->markProcessing($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');
    $service->markSucceeded($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');

    expect($backup->refresh())
        ->status->toBe(BackupStatus::Succeeded)
        ->database_sha256->toBe(str_repeat('a', 64))
        ->media_sha256->toBe(str_repeat('b', 64))
        ->database_size_bytes->toBe(1200)
        ->media_size_bytes->toBe(3400)
        ->retention_until->isAfter(now())->toBeTrue();
    $requestedAudit = AuditLog::query()->where('action', 'operations.backup.requested')->firstOrFail();
    expect($requestedAudit->new_values)->toMatchArray([
        'backup_pair_uuid' => $backup->backup_pair_uuid,
        'status' => BackupStatus::Pending->value,
        'database_location_alias' => 'backup-db-20260802',
        'media_location_alias' => 'backup-media-20260802',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
        'retention_until' => $backup->retention_until->toIso8601String(),
        'restore_tested_at' => null,
        'restore_verification_result' => null,
        'restore_verification_target_alias' => null,
        'restore_verification_evidence_reference' => '[REDACTED]',
    ])->and($requestedAudit->new_values)
        ->not->toHaveKey('path')
        ->not->toHaveKey('operator_key');

    $succeededAudit = AuditLog::query()->where('action', 'operations.backup.succeeded')->firstOrFail();
    expect($succeededAudit->new_values)->toMatchArray([
        'status' => BackupStatus::Succeeded->value,
        'retention_until' => $backup->retention_until->toIso8601String(),
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
    ]);
});

it('returns the persisted backup without duplicate audit for an identical actor-key-payload retry', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);
    $first = new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::parse('2026-09-02T12:00:00+07:00'),
        operatorKey: 'backup-operator-key-replay',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    );

    $created = $service->request($first);
    $replayed = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::parse('2026-09-02T05:00:00Z'),
        operatorKey: 'backup-operator-key-replay',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    expect($replayed->is($created))->toBeTrue()
        ->and(BackupLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);
});

it('fails closed when an actor reuses an operator key with a different canonical payload', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);
    $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::parse('2026-09-02T12:00:00+07:00'),
        operatorKey: 'backup-operator-key-conflict',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    expect(fn () => $service->request(new BackupRequest(
        actor: $actor,
        artifacts: new BackupArtifactPair(
            database: new BackupArtifact('backup-db-different', str_repeat('a', 64), 1200),
            media: new BackupArtifact('backup-media-20260802', str_repeat('b', 64), 3400),
        ),
        retentionUntil: CarbonImmutable::parse('2026-09-02T12:00:00+07:00'),
        operatorKey: 'backup-operator-key-conflict',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    )))->toThrow(BackupRequestConflict::class, 'payload berbeda');

    expect(BackupLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);
});

it('keeps the same operator key independent between actors', function (): void {
    $firstActor = User::factory()->create();
    $secondActor = User::factory()->create();
    grantOperationPermission($firstActor, 'backup.run');
    grantOperationPermission($secondActor, 'backup.run');
    $service = app(BackupLifecycleService::class);

    $first = $service->request(new BackupRequest(
        actor: $firstActor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::parse('2026-09-02T12:00:00+07:00'),
        operatorKey: 'backup-operator-key-actor-scope',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));
    $second = $service->request(new BackupRequest(
        actor: $secondActor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::parse('2026-09-02T12:00:00+07:00'),
        operatorKey: 'backup-operator-key-actor-scope',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    expect($second->is($first))->toBeFalse()
        ->and(BackupLog::query()->count())->toBe(2)
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(2);
});

it('keeps historical backup rows readable without an operator key', function (): void {
    $actor = User::factory()->create();
    $backup = BackupLog::query()->create([
        'backup_pair_uuid' => '018f4ca4-2e67-7c16-a455-8f610f6f5642',
        'initiated_by' => $actor->id,
        'status' => BackupStatus::Pending,
        'database_location_alias' => 'backup-db-legacy',
        'media_location_alias' => 'backup-media-legacy',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
        'retention_until' => CarbonImmutable::now()->addDays(30),
        'started_at' => CarbonImmutable::now(),
    ]);

    expect($backup->refresh()->operator_key)->toBeNull()
        ->and($backup->request_payload_hash)->toBeNull();
});

it('rejects invalid correlation IDs in the direct backup lifecycle API', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);

    expect(fn () => new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-invalid-correlation',
        correlationId: 'not-a-uuid-correlation-id',
    ))->toThrow(InvalidArgumentException::class);

    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    expect(fn () => $service->markProcessing($actor, $backup, 'not-a-uuid-correlation-id'))
        ->toThrow(InvalidArgumentException::class);
    expect($backup->refresh()->status)->toBe(BackupStatus::Pending);
});

it('uses the locked persisted status for stale transition requests and treats duplicate targets as safe no-ops', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);
    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    BackupLog::query()->whereKey($backup->getKey())->update(['status' => BackupStatus::Processing->value]);

    $service->markProcessing($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');
    $service->markProcessing($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');
    $service->markSucceeded($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');

    expect(BackupLog::query()->findOrFail($backup->getKey())->status)->toBe(BackupStatus::Succeeded);
    expect(AuditLog::query()->where('action', 'operations.backup.processing')->count())->toBe(0);
    expect(AuditLog::query()->where('action', 'operations.backup.succeeded')->count())->toBe(1);
});

it('rejects conflicting transitions from the locked persisted status without mutation or audit', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);
    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));

    expect(fn () => $service->markSucceeded($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642'))
        ->toThrow(LogicException::class);

    expect(BackupLog::query()->findOrFail($backup->getKey())->status)->toBe(BackupStatus::Pending);
    expect(AuditLog::query()->where('action', 'operations.backup.succeeded')->count())->toBe(0);
});

it('uses the locked persisted status for stale restore verification requests', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    grantOperationPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));
    BackupLog::query()->whereKey($backup->getKey())->update(['status' => BackupStatus::Succeeded->value]);

    $service->recordRestoreVerification(
        actor: $actor,
        backup: $backup,
        verificationTargetAlias: 'verify-2026-08-02-lane-d',
        evidenceReference: operationEvidenceReference(),
        passed: true,
        correlationId: operationCorrelationId(),
    );

    $persistedBackup = BackupLog::query()->findOrFail($backup->getKey());
    expect($persistedBackup)
        ->restore_verification_result->toBe(BackupRestoreVerificationResult::Passed)
        ->restore_verification_target_alias->toBe('verify-2026-08-02-lane-d')
        ->restore_verification_evidence_reference->toBe(operationEvidenceReference());

    $audit = AuditLog::query()->where('action', 'operations.backup.restore_verified')->firstOrFail();
    expect($audit->new_values)->toMatchArray([
        'status' => BackupStatus::Succeeded->value,
        'retention_until' => $persistedBackup->retention_until->toIso8601String(),
        'restore_tested_at' => $persistedBackup->restore_tested_at->toIso8601String(),
        'restore_verification_result' => BackupRestoreVerificationResult::Passed->value,
        'restore_verification_target_alias' => 'verify-2026-08-02-lane-d',
        'restore_verification_evidence_reference' => '[REDACTED]',
        'database_location_alias' => 'backup-db-20260802',
        'media_location_alias' => 'backup-media-20260802',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
    ])->and($audit->new_values)->not->toHaveKey('path');
});

it('rolls back a backup request when audit recording fails', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    $service = app(BackupLifecycleService::class);
    $shouldFailAudit = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFailAudit): void {
        if ($shouldFailAudit && str_contains(strtolower($query->sql), 'audit_logs')) {
            throw new RuntimeException('audit failure');
        }
    });

    try {
        expect(fn () => $service->request(new BackupRequest(
            actor: $actor,
            artifacts: pairedBackupArtifacts(),
            retentionUntil: CarbonImmutable::now()->addDays(30),
            operatorKey: 'backup-operator-key-audit-failure',
            correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
        )))->toThrow(RuntimeException::class, 'audit failure');
    } finally {
        $shouldFailAudit = false;
    }

    expect(BackupLog::query()->count())->toBe(0);
    expect(AuditLog::query()->count())->toBe(0);
});

it('records failed isolated restore verification without changing backup artifact metadata', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    grantOperationPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = completedBackup($service, $actor);
    $artifactMetadata = $backup->only([
        'status',
        'database_location_alias',
        'media_location_alias',
        'database_sha256',
        'media_sha256',
        'database_size_bytes',
        'media_size_bytes',
        'retention_until',
        'started_at',
        'finished_at',
    ]);

    $service->recordRestoreVerification(
        actor: $actor,
        backup: $backup,
        verificationTargetAlias: 'verify-2026-08-02-lane-d',
        evidenceReference: operationEvidenceReference(),
        passed: false,
        correlationId: operationCorrelationId(),
    );

    $persistedBackup = $backup->refresh();
    expect($persistedBackup->only(array_keys($artifactMetadata)))->toEqual($artifactMetadata)
        ->and($persistedBackup->restore_verification_result)->toBe(BackupRestoreVerificationResult::Failed)
        ->and($persistedBackup->restore_verification_target_alias)->toBe('verify-2026-08-02-lane-d')
        ->and($persistedBackup->restore_verification_evidence_reference)->toBe(operationEvidenceReference())
        ->and($persistedBackup->restore_tested_at)->not->toBeNull();

    $audit = AuditLog::query()->where('action', 'operations.backup.restore_verified')->firstOrFail();
    expect($audit->actor_id)->toBe($actor->id)
        ->and($audit->actor_type)->toBe('user')
        ->and($audit->auditable_type)->toBe(BackupLog::class)
        ->and($audit->auditable_id)->toBe($backup->id)
        ->and($audit->correlation_id)->toBe(operationCorrelationId())
        ->and($audit->new_values['restore_verification_evidence_reference'])->toBe('[REDACTED]');
});

it('rejects missing or malformed restore evidence before mutation', function (string $evidenceReference): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    grantOperationPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = completedBackup($service, $actor);
    $before = $backup->getAttributes();

    expect(fn () => $service->recordRestoreVerification(
        actor: $actor,
        backup: $backup,
        verificationTargetAlias: 'verify-2026-08-02-lane-d',
        evidenceReference: $evidenceReference,
        passed: true,
        correlationId: operationCorrelationId(),
    ))->toThrow(InvalidArgumentException::class);

    expect($backup->refresh()->getAttributes())->toEqual($before)
        ->and(AuditLog::query()->where('action', 'operations.backup.restore_verified')->count())->toBe(0);
})->with([
    'wrong length' => 'short-evidence',
    'path' => '/'.str_repeat('e', 42),
    'url' => 'https://bank-sampah.test/evidence/'.str_repeat('e', 43),
]);

it('rejects a missing restore evidence reference before target validation or mutation', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    grantOperationPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = completedBackup($service, $actor);
    $before = $backup->getAttributes();

    expect(fn () => $service->recordRestoreVerification(
        actor: $actor,
        backup: $backup,
        verificationTargetAlias: 'production-restore',
        evidenceReference: '',
        passed: true,
        correlationId: operationCorrelationId(),
    ))->toThrow(InvalidArgumentException::class);

    expect($backup->refresh()->getAttributes())->toEqual($before)
        ->and(AuditLog::query()->where('action', 'operations.backup.restore_verified')->count())->toBe(0);
});

it('rejects a production-like target for restore verification', function (): void {
    $actor = User::factory()->create();
    grantOperationPermission($actor, 'backup.run');
    grantOperationPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = completedBackup($service, $actor);

    $service->recordRestoreVerification(
        actor: $actor,
        backup: $backup,
        verificationTargetAlias: 'production-restore',
        evidenceReference: operationEvidenceReference(),
        passed: true,
        correlationId: operationCorrelationId(),
    );
})->throws(InvalidArgumentException::class);

it('rejects an unauthorized backup action', function (): void {
    $service = app(BackupLifecycleService::class);

    $service->request(new BackupRequest(
        actor: User::factory()->create(),
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-20260802',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));
})->throws(AuthorizationException::class);

it('rejects a document root outside the public directory without exposing paths', function (): void {
    config()->set([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(random_bytes(32)),
        'queue.default' => 'sync',
        'operations.deployment.release_root' => base_path(),
        'operations.deployment.document_root' => base_path(),
        'operations.deployment.vite_manifest' => public_path('build/manifest.json'),
        'operations.queue.worker_mode' => 'none',
    ]);

    $result = app(DeploymentTopologyValidator::class)->validate();

    expect($result->issueCodes())->toContain('document_root_outside_public');
    expect(implode('|', $result->issueCodes()))->not->toContain(base_path());
});

it('accepts the production HTTPS and secure-cookie runtime contract', function (): void {
    config()->set([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'https://example.invalid',
        'session.secure' => true,
        'queue.default' => 'sync',
        'operations.deployment.release_root' => base_path(),
        'operations.deployment.document_root' => public_path(),
        'operations.deployment.vite_manifest' => public_path('build/manifest.json'),
        'operations.queue.worker_mode' => 'none',
    ]);

    expect(app(DeploymentTopologyValidator::class)->validate()->passes())->toBeTrue();
});

it('rejects a non-HTTPS URL or insecure session cookie in production', function (): void {
    config()->set([
        'app.env' => 'production',
        'app.debug' => false,
        'app.key' => 'base64:'.base64_encode(str_repeat('k', 32)),
        'app.url' => 'http://example.invalid',
        'session.secure' => false,
        'queue.default' => 'sync',
        'operations.deployment.release_root' => base_path(),
        'operations.deployment.document_root' => public_path(),
        'operations.deployment.vite_manifest' => public_path('build/manifest.json'),
        'operations.queue.worker_mode' => 'none',
    ]);

    expect(app(DeploymentTopologyValidator::class)->validate()->issueCodes())
        ->toContain('https_application_url_required')
        ->toContain('secure_session_cookie_required');
});

it('uses the shared eligibility predicate for rollback and operational health', function (): void {
    $backup = BackupLog::query()->create([
        'backup_pair_uuid' => (string) Str::uuid(),
        'initiated_by' => User::factory()->create()->id,
        'status' => BackupStatus::Succeeded,
        'database_location_alias' => 'backup-db-shared-contract',
        'media_location_alias' => 'backup-media-shared-contract',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('a', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 1200,
        'retention_until' => CarbonImmutable::now()->addHour(),
        'started_at' => CarbonImmutable::now()->subMinute(),
        'finished_at' => CarbonImmutable::now(),
        'restore_tested_at' => CarbonImmutable::now(),
        'restore_verification_result' => BackupRestoreVerificationResult::Passed,
        'restore_verification_target_alias' => 'verify-shared-contract',
        'restore_verification_evidence_reference' => operationEvidenceReference(),
    ]);
    $rollback = app(RollbackPlanValidator::class)->validate(new RollbackPlan(
        schemaCompatible: false,
        requiresDatabaseMediaRestore: true,
        automaticMigrationRollbackRequested: false,
        verifiedBackup: $backup,
    ));

    expect($rollback->passes())->toBeTrue()
        ->and(app(OperationalHealthService::class)->check()->toArray()['verified_backup']['status'])->toBe('ok');
});

it('sanitizes unexpected rollback infrastructure failures at the command boundary', function (): void {
    $shouldFail = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
        if ($shouldFail && str_contains(strtolower($query->sql), 'backup_logs')) {
            throw new RuntimeException('SQL path and secret detail');
        }
    });

    try {
        $this->artisan('operations:validate-rollback', [
            '--requires-restore' => true,
            '--backup-id' => '1',
        ])
            ->expectsOutputToContain('rollback-plan: infrastructure-unavailable')
            ->doesntExpectOutputToContain('SQL path and secret detail')
            ->doesntExpectOutputToContain(base_path())
            ->assertExitCode(Command::FAILURE);
    } finally {
        $shouldFail = false;
    }
});

it('rejects a rollback plan that omits a required paired database and media restore', function (): void {
    $result = app(RollbackPlanValidator::class)->validate(new RollbackPlan(
        schemaCompatible: false,
        requiresDatabaseMediaRestore: false,
        automaticMigrationRollbackRequested: false,
        verifiedBackup: null,
    ));

    expect($result->passes())->toBeFalse();
    expect($result->issueCodes())->toContain('database_media_restore_required');
});

it('rejects automatic destructive migration rollback and accepts a code-compatible plan', function (): void {
    $validator = app(RollbackPlanValidator::class);
    $rejected = $validator->validate(new RollbackPlan(
        schemaCompatible: true,
        requiresDatabaseMediaRestore: false,
        automaticMigrationRollbackRequested: true,
        verifiedBackup: null,
    ));
    $accepted = $validator->validate(new RollbackPlan(
        schemaCompatible: true,
        requiresDatabaseMediaRestore: false,
        automaticMigrationRollbackRequested: false,
        verifiedBackup: null,
    ));

    expect($rejected->issueCodes())->toContain('automatic_destructive_migration_rollback_rejected');
    expect($accepted->passes())->toBeTrue();
});

it('prints only sanitized read-only smoke checks', function (): void {
    config()->set([
        'operations.scheduler.topology' => 'cron',
        'operations.deployment.vite_manifest' => public_path('build/manifest.json'),
    ]);

    $this->artisan('operations:smoke')
        ->expectsOutputToContain('health-route:')
        ->expectsOutputToContain('db-connectivity:')
        ->expectsOutputToContain('private-disk:')
        ->doesntExpectOutputToContain(storage_path('app/media'))
        ->doesntExpectOutputToContain(base_path())
        ->assertExitCode(Command::FAILURE);
});

function grantOperationPermission(User $user, string $permissionName): void
{
    $role = Role::query()->firstOrCreate(['name' => 'operations-test-role'], ['description' => 'Role test operasi']);
    $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => 'Permission test operasi']);
    $role->permissions()->syncWithoutDetaching([$permission->id => ['reason' => 'test']]);
    $user->roles()->syncWithoutDetaching([$role->id => ['reason' => 'test']]);
}

function pairedBackupArtifacts(): BackupArtifactPair
{
    return new BackupArtifactPair(
        database: new BackupArtifact('backup-db-20260802', str_repeat('a', 64), 1200),
        media: new BackupArtifact('backup-media-20260802', str_repeat('b', 64), 3400),
    );
}

function operationCorrelationId(): string
{
    return '018f4ca4-2e67-7c16-a455-8f610f6f5642';
}

function operationEvidenceReference(): string
{
    return str_repeat('e', 43);
}

function completedBackup(BackupLifecycleService $service, User $actor): BackupLog
{
    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: pairedBackupArtifacts(),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-completed',
        correlationId: '018f4ca4-2e67-7c16-a455-8f610f6f5642',
    ));
    $service->markProcessing($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');
    $service->markSucceeded($actor, $backup, '018f4ca4-2e67-7c16-a455-8f610f6f5642');

    return BackupLog::query()->findOrFail($backup->getKey());
}
