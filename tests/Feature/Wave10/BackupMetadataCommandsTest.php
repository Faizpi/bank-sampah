<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('records a backup pair from metadata through the discovered Artisan command', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');

    $this->artisan('operations:backup-record', backupRecordOptions($actor))
        ->expectsOutputToContain('backup-pair: metadata-recorded')
        ->expectsOutputToContain('backup-id:')
        ->assertExitCode(Command::SUCCESS);

    $backup = BackupLog::query()->firstOrFail();
    expect($backup->status)->toBe(BackupStatus::Pending)
        ->and($backup->database_location_alias)->toBe('backup-db-20260803')
        ->and($backup->media_location_alias)->toBe('backup-media-20260803')
        ->and($backup->database_sha256)->toBe(str_repeat('a', 64))
        ->and($backup->database_size_bytes)->toBe(1200)
        ->and($backup->retention_until->isFuture())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);

    $audit = AuditLog::query()->where('action', 'operations.backup.requested')->firstOrFail();
    expect($audit->new_values)->toMatchArray([
        'status' => BackupStatus::Pending->value,
        'database_location_alias' => 'backup-db-20260803',
        'media_location_alias' => 'backup-media-20260803',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
        'retention_until' => $backup->retention_until->toIso8601String(),
        'restore_tested_at' => null,
        'restore_verification_result' => null,
        'restore_verification_target_alias' => null,
        'restore_verification_evidence_reference' => '[REDACTED]',
    ])->and($audit->new_values)
        ->not->toHaveKey('path')
        ->not->toHaveKey('operator_key');
});

it('requires an explicit operator key for new backup recording', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $options = backupRecordOptions($actor);
    unset($options['--operator-key']);

    $this->artisan('operations:backup-record', $options)
        ->expectsOutputToContain('backup-pair: invalid-input')
        ->assertExitCode(Command::FAILURE);

    expect(BackupLog::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('rejects a non-UUID correlation ID for backup recording before persistence', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $options = backupRecordOptions($actor);
    $options['--correlation-id'] = 'backup-command-correlation';

    $this->artisan('operations:backup-record', $options)
        ->expectsOutputToContain('backup-pair: invalid-input')
        ->assertExitCode(Command::FAILURE);

    expect(BackupLog::query()->count())->toBe(0)
        ->and(AuditLog::query()->count())->toBe(0);
});

it('fails closed when the operator key is reused with a different payload', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $first = backupRecordOptions($actor);
    $second = $first;
    $second['--database-alias'] = 'backup-db-different';

    $this->artisan('operations:backup-record', $first)->assertExitCode(Command::SUCCESS);
    $this->artisan('operations:backup-record', $second)
        ->expectsOutputToContain('backup-pair: operator-key-conflict')
        ->assertExitCode(Command::FAILURE);

    expect(BackupLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);
});

it('fails closed when the explicit actor lacks backup.run', function (): void {
    $actor = User::factory()->create();

    $this->artisan('operations:backup-record', backupRecordOptions($actor))
        ->expectsOutputToContain('backup-pair: permission-denied')
        ->doesntExpectOutputToContain(base_path())
        ->assertExitCode(Command::FAILURE);

    expect(BackupLog::query()->count())->toBe(0);
});

it('sanitizes unexpected backup infrastructure failures at the command boundary', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $shouldFail = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
        if ($shouldFail && str_contains(strtolower($query->sql), 'audit_logs')) {
            throw new RuntimeException('SQL path and secret detail');
        }
    });

    try {
        $this->artisan('operations:backup-record', backupRecordOptions($actor))
            ->expectsOutputToContain('backup-pair: infrastructure-unavailable')
            ->doesntExpectOutputToContain('SQL path and secret detail')
            ->doesntExpectOutputToContain(base_path())
            ->assertExitCode(Command::FAILURE);
    } finally {
        $shouldFail = false;
    }
});

it('replays the same backup command without duplicate row or request audit', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $options = backupRecordOptions($actor);

    $this->artisan('operations:backup-record', $options)->assertExitCode(Command::SUCCESS);
    $this->artisan('operations:backup-record', $options)->assertExitCode(Command::SUCCESS);

    expect(BackupLog::query()->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);
});

it('rejects invalid backup metadata before persistence', function (): void {
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    $options = backupRecordOptions($actor);
    $options['--database-alias'] = base_path('database-dump.sql');
    $options['--database-sha256'] = 'not-a-checksum';

    $this->artisan('operations:backup-record', $options)
        ->expectsOutputToContain('backup-pair: invalid-input')
        ->doesntExpectOutputToContain(base_path())
        ->assertExitCode(Command::FAILURE);

    expect(BackupLog::query()->count())->toBe(0);
});

it('records restore verification metadata with an explicit result flag', function (): void {
    [$actor, $backup] = commandCompletedBackup();
    $options = [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => (string) $backup->id,
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--passed' => true,
        '--correlation-id' => commandCorrelationId(),
    ];

    $this->artisan('operations:backup-restore-verify', $options)
        ->expectsOutputToContain('restore-verification: metadata-recorded')
        ->expectsOutputToContain('result: passed')
        ->assertExitCode(Command::SUCCESS);

    expect($backup->refresh()->restore_verification_result)->toBe(BackupRestoreVerificationResult::Passed)
        ->and($backup->restore_verification_target_alias)->toBe('verify-2026-08-03-lane-d')
        ->and($backup->restore_verification_evidence_reference)->toBe(backupEvidenceReference())
        ->and($backup->getHidden())->toContain('restore_verification_evidence_reference')
        ->and($backup->toArray())->not->toHaveKey('restore_verification_evidence_reference')
        ->and(AuditLog::query()->where('action', 'operations.backup.restore_verified')->count())->toBe(1);

    $audit = AuditLog::query()->where('action', 'operations.backup.restore_verified')->firstOrFail();
    expect($audit->actor_id)->toBe($actor->id)
        ->and($audit->actor_type)->toBe('user')
        ->and($audit->auditable_type)->toBe(BackupLog::class)
        ->and($audit->auditable_id)->toBe($backup->id)
        ->and($audit->correlation_id)->toBe(commandCorrelationId());

    $audit = AuditLog::query()->where('action', 'operations.backup.restore_verified')->firstOrFail();
    expect($audit->new_values)->toMatchArray([
        'status' => BackupStatus::Succeeded->value,
        'retention_until' => $backup->retention_until->toIso8601String(),
        'restore_tested_at' => $backup->restore_tested_at->toIso8601String(),
        'restore_verification_result' => BackupRestoreVerificationResult::Passed->value,
        'restore_verification_target_alias' => 'verify-2026-08-03-lane-d',
        'restore_verification_evidence_reference' => '[REDACTED]',
        'database_location_alias' => 'backup-db-20260803',
        'media_location_alias' => 'backup-media-20260803',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
    ])->and($audit->new_values)->not->toHaveKey('path');
});

it('sanitizes unexpected restore infrastructure failures at the command boundary', function (): void {
    [$actor, $backup] = commandCompletedBackup();
    $shouldFail = true;
    DB::listen(function (QueryExecuted $query) use (&$shouldFail): void {
        if ($shouldFail && str_contains(strtolower($query->sql), 'audit_logs')) {
            throw new RuntimeException('SQL path and secret detail');
        }
    });

    try {
        $this->artisan('operations:backup-restore-verify', [
            '--actor-id' => (string) $actor->id,
            '--backup-id' => (string) $backup->id,
            '--verification-target-alias' => 'verify-2026-08-03-lane-d',
            '--evidence-reference' => backupEvidenceReference(),
            '--passed' => true,
            '--correlation-id' => commandCorrelationId(),
        ])
            ->expectsOutputToContain('restore-verification: infrastructure-unavailable')
            ->doesntExpectOutputToContain('SQL path and secret detail')
            ->doesntExpectOutputToContain(base_path())
            ->assertExitCode(Command::FAILURE);
    } finally {
        $shouldFail = false;
    }
});

it('rejects a non-UUID correlation ID for restore verification before mutation', function (): void {
    [$actor, $backup] = commandCompletedBackup();

    $this->artisan('operations:backup-restore-verify', [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => (string) $backup->id,
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--passed' => true,
        '--correlation-id' => 'restore-command-correlation',
    ])
        ->expectsOutputToContain('restore-verification: invalid-input')
        ->assertExitCode(Command::FAILURE);

    expect($backup->refresh()->restore_verification_result)->toBeNull()
        ->and(AuditLog::query()->where('action', 'operations.backup.restore_verified')->count())->toBe(0);
});

it('rejects missing or malformed evidence before restore mutation', function (array $changes): void {
    [$actor, $backup] = commandCompletedBackup();
    $options = [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => (string) $backup->id,
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--passed' => true,
        '--correlation-id' => commandCorrelationId(),
    ];
    foreach ($changes as $key => $value) {
        if ($value === null) {
            unset($options[$key]);

            continue;
        }

        $options[$key] = $value;
    }

    $this->artisan('operations:backup-restore-verify', $options)
        ->expectsOutputToContain('restore-verification: invalid-input')
        ->assertExitCode(Command::FAILURE);

    expect($backup->refresh()->restore_verification_result)->toBeNull()
        ->and($backup->restore_verification_evidence_reference)->toBeNull()
        ->and(AuditLog::query()->where('action', 'operations.backup.restore_verified')->count())->toBe(0);
})->with([
    'missing' => [['--evidence-reference' => null]],
    'wrong length' => [['--evidence-reference' => 'short-evidence']],
    'path' => [['--evidence-reference' => '/'.str_repeat('e', 42)]],
    'url' => [['--evidence-reference' => 'https://bank-sampah.test/evidence/'.str_repeat('e', 43)]],
]);

it('fails restore verification without backup.restore', function (): void {
    [$owner, $backup] = commandCompletedBackup();
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');

    $this->artisan('operations:backup-restore-verify', [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => (string) $backup->id,
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--failed' => true,
        '--correlation-id' => commandCorrelationId(),
    ])
        ->expectsOutputToContain('restore-verification: permission-denied')
        ->assertExitCode(Command::FAILURE);

    expect($backup->refresh()->restore_verification_result)->toBeNull()
        ->and($owner->id)->toBeInt();
});

it('rejects ambiguous restore result flags and a missing backup', function (): void {
    [$actor, $backup] = commandCompletedBackup();

    $this->artisan('operations:backup-restore-verify', [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => (string) $backup->id,
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--passed' => true,
        '--failed' => true,
        '--correlation-id' => commandCorrelationId(),
    ])
        ->expectsOutputToContain('restore-verification: invalid-input')
        ->assertExitCode(Command::FAILURE);

    $this->artisan('operations:backup-restore-verify', [
        '--actor-id' => (string) $actor->id,
        '--backup-id' => '999999',
        '--verification-target-alias' => 'verify-2026-08-03-lane-d',
        '--evidence-reference' => backupEvidenceReference(),
        '--passed' => true,
        '--correlation-id' => commandCorrelationId(),
    ])
        ->expectsOutputToContain('restore-verification: backup-not-found')
        ->assertExitCode(Command::FAILURE);
});

it('exposes both metadata commands through Artisan discovery', function (): void {
    $this->artisan('list')
        ->expectsOutputToContain('operations:backup-record')
        ->expectsOutputToContain('operations:backup-restore-verify')
        ->assertExitCode(Command::SUCCESS);
});

it('contains no filesystem, shell, network, or artifact-content operations', function (): void {
    $sources = [
        file_get_contents(app_path('Console/Commands/RecordBackupPair.php')),
        file_get_contents(app_path('Console/Commands/RecordRestoreVerification.php')),
    ];

    foreach ($sources as $source) {
        expect($source)->toBeString()
            ->and($source)->not->toContain('Storage::')
            ->and($source)->not->toContain('Process::')
            ->and($source)->not->toContain('shell_exec')
            ->and($source)->not->toContain('exec(')
            ->and($source)->not->toContain('system(')
            ->and($source)->not->toContain('file_get_contents')
            ->and($source)->not->toContain('file_put_contents')
            ->and($source)->not->toContain('curl_')
            ->and($source)->not->toContain('database-dump');
    }
});

/** @return array<string, string|bool> */
function backupRecordOptions(User $actor): array
{
    return [
        '--actor-id' => (string) $actor->id,
        '--database-alias' => 'backup-db-20260803',
        '--database-sha256' => str_repeat('a', 64),
        '--database-size-bytes' => '1200',
        '--media-alias' => 'backup-media-20260803',
        '--media-sha256' => str_repeat('b', 64),
        '--media-size-bytes' => '3400',
        '--retention-until' => '2026-09-02T12:00:00+07:00',
        '--operator-key' => 'backup-operator-key-20260803',
        '--correlation-id' => commandCorrelationId(),
    ];
}

function commandCompletedBackup(): array
{
    $actor = User::factory()->create();
    grantBackupCommandPermission($actor, 'backup.run');
    grantBackupCommandPermission($actor, 'backup.restore');
    $service = app(BackupLifecycleService::class);
    $backup = $service->request(new BackupRequest(
        actor: $actor,
        artifacts: new BackupArtifactPair(
            database: new BackupArtifact('backup-db-20260803', str_repeat('a', 64), 1200),
            media: new BackupArtifact('backup-media-20260803', str_repeat('b', 64), 3400),
        ),
        retentionUntil: CarbonImmutable::now()->addDays(30),
        operatorKey: 'backup-operator-key-completed',
        correlationId: commandCorrelationId(),
    ));
    $service->markProcessing($actor, $backup, commandCorrelationId());
    $service->markSucceeded($actor, $backup, commandCorrelationId());

    return [$actor, $backup];
}

function commandCorrelationId(): string
{
    return '018f4ca4-2e67-7c16-a455-8f610f6f5642';
}

function backupEvidenceReference(): string
{
    return str_repeat('e', 43);
}

function grantBackupCommandPermission(User $user, string $permissionName): void
{
    $role = Role::query()->create(['name' => 'backup-command-test-role-'.$user->id.'-'.$permissionName, 'description' => 'Role test command backup']);
    $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => 'Permission test command backup']);
    $role->permissions()->syncWithoutDetaching([$permission->id => ['reason' => 'test']]);
    $user->roles()->syncWithoutDetaching([$role->id => ['reason' => 'test']]);
}
