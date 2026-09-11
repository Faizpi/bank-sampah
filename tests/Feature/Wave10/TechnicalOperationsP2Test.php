<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Models\AuditLog;
use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\AuditReconciliation\Services\AuditRetentionService;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Services\BackupArtifact;
use App\Domain\Operations\Services\BackupArtifactPair;
use App\Domain\Operations\Services\BackupLifecycleService;
use App\Domain\Operations\Services\BackupRequest;
use App\Domain\Operations\Services\OperationalSettingsService;
use App\Filament\Pages\OperationsDashboard;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    App::maintenanceMode()->deactivate();
});

it('updates only allowlisted non-secret settings and audits the sanitized values', function (): void {
    $actor = operationsP2User('system.settings.manage');
    $service = app(OperationalSettingsService::class);

    $service->update($actor, [
        'queue_backlog_threshold' => 25,
        'backup_max_age_hours' => 48,
    ]);

    expect(config('operations.health.queue_backlog_threshold'))->toBe(25)
        ->and(config('operations.health.backup_max_age_hours'))->toBe(48)
        ->and(AuditLog::query()->where('action', 'system.settings.updated')->count())->toBe(1)
        ->and(AuditLog::query()->where('action', 'system.settings.updated')->firstOrFail()->new_values)->toBe([
            'queue_backlog_threshold' => 25,
            'backup_max_age_hours' => 48,
        ]);
});

it('maps each invalid technical setting only to its matching manual field', function (): void {
    $actor = operationsP2User('system.settings.manage');
    auth()->login($actor);
    $page = app(OperationsDashboard::class);
    $page->settings = [
        'queue_backlog_threshold' => 0,
        'backup_max_age_hours' => 48,
    ];

    try {
        $page->saveSettings();
        test()->fail('Expected invalid settings validation.');
    } catch (ValidationException $exception) {
        expect($exception->errors())
            ->toHaveKey('settings.queue_backlog_threshold')
            ->not->toHaveKey('settings.backup_max_age_hours')
            ->and($exception->errors()['settings.queue_backlog_threshold'])
            ->toBe(['Nilai pengaturan berada di luar batas.']);
    }
});

it('maps technical dashboard backup validation errors to the manual field properties', function (): void {
    $page = app(OperationsDashboard::class);
    $method = new ReflectionMethod($page, 'positiveInteger');

    try {
        $method->invoke($page, '0', 'Ukuran basis data', 'backupDatabaseSizeBytes');
        test()->fail('Expected invalid backup size validation.');
    } catch (ReflectionException $exception) {
        throw $exception;
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('backupDatabaseSizeBytes');
    }
});

it('rejects secret-like or unknown settings without mutation or audit', function (): void {
    $actor = operationsP2User('system.settings.manage');
    $service = app(OperationalSettingsService::class);
    $before = config('operations.health.queue_backlog_threshold');

    expect(fn () => $service->update($actor, ['app_key' => 'secret']))
        ->toThrow(ValidationException::class);

    expect(config('operations.health.queue_backlog_threshold'))->toBe($before)
        ->and(AuditLog::query()->where('action', 'system.settings.updated')->count())->toBe(0);
});

it('exposes the technical dashboard only to users with a settled technical permission union', function (): void {
    $viewer = operationsP2User('backup.view');
    $none = User::factory()->create();

    auth()->login($viewer);
    expect(OperationsDashboard::canAccess())->toBeTrue();
    auth()->login($none);
    expect(OperationsDashboard::canAccess())->toBeFalse();
});

it('closes maintenance dialog only after a successful operation', function (): void {
    $actor = operationsP2User('system.maintenance');

    Livewire::actingAs($actor)
        ->test(OperationsDashboard::class)
        ->set('maintenanceReason', 'Pemeliharaan terjadwal untuk pembaruan aplikasi.')
        ->call('toggleMaintenance')
        ->assertDispatched('close-dialog', id: 'maintenance-dialog');
});

it('does not close maintenance dialog when validation fails', function (): void {
    $actor = operationsP2User('system.maintenance');

    Livewire::actingAs($actor)
        ->test(OperationsDashboard::class)
        ->set('maintenanceReason', '')
        ->call('toggleMaintenance')
        ->assertHasErrors('maintenanceReason')
        ->assertNotDispatched('close-dialog');
});

it('fails closed for settings and maintenance without their technical permissions', function (): void {
    $actor = User::factory()->create();

    expect(fn () => app(OperationalSettingsService::class)->update($actor, ['queue_backlog_threshold' => 20]))
        ->toThrow(AuthorizationException::class);
    expect(fn () => app(OperationalSettingsService::class)->setMaintenance($actor, true, 'Scheduled migration'))
        ->toThrow(AuthorizationException::class);
});

it('dispatches close only after successful audit retention and keeps stale errors in the dialog', function (): void {
    $actor = operationsP2User('audit.retention.execute');
    $cutoff = now()->subDay()->toDateString();

    Livewire::actingAs($actor)
        ->test(OperationsDashboard::class)
        ->set('retentionBefore', $cutoff)
        ->call('executeRetention')
        ->assertHasErrors('retentionBefore')
        ->assertNotDispatched('close-dialog')
        ->call('previewRetention')
        ->call('executeRetention')
        ->assertDispatched('close-dialog', id: 'audit-retention-dialog');
});

it('dispatches close only after successful media retention and keeps stale errors in the dialog', function (): void {
    $actor = operationsP2User('media.retention.execute');
    $cutoff = now()->subDays(180)->toDateString();

    Livewire::actingAs($actor)
        ->test(OperationsDashboard::class)
        ->set('mediaRetentionBefore', $cutoff)
        ->call('executeMediaRetention')
        ->assertHasErrors('mediaRetentionBefore')
        ->assertNotDispatched('close-dialog')
        ->call('previewMediaRetention')
        ->call('executeMediaRetention')
        ->assertDispatched('close-dialog', id: 'media-retention-dialog');
});

it('dispatches close only after valid backup metadata is recorded', function (): void {
    $actor = operationsP2User('backup.run');
    $component = Livewire::actingAs($actor)->test(OperationsDashboard::class)
        ->set('backupDatabaseAlias', 'db-2026-08-23')
        ->set('backupMediaAlias', 'media-2026-08-23')
        ->set('backupDatabaseSha256', str_repeat('a', 64))
        ->set('backupMediaSha256', str_repeat('b', 64))
        ->set('backupDatabaseSizeBytes', '0')
        ->set('backupMediaSizeBytes', '200')
        ->set('backupRetentionUntil', now()->addDay()->toDateTimeLocalString())
        ->set('backupOperatorKey', 'operations-p2-modal-key');

    $component->call('recordBackupMetadata')
        ->assertHasErrors('backupDatabaseSizeBytes')
        ->assertNotDispatched('close-dialog')
        ->set('backupDatabaseSizeBytes', '100')
        ->call('recordBackupMetadata')
        ->assertDispatched('close-dialog', id: 'backup-metadata-dialog');
});

it('keeps invalid restore verification visible and dispatches close after success', function (): void {
    $actor = operationsP2User('backup.run', 'backup.restore');
    $backup = app(BackupLifecycleService::class)->request(new BackupRequest(
        actor: $actor,
        artifacts: new BackupArtifactPair(
            database: new BackupArtifact('db-restore', str_repeat('c', 64), 100),
            media: new BackupArtifact('media-restore', str_repeat('d', 64), 200),
        ),
        retentionUntil: CarbonImmutable::now()->addDay(),
        operatorKey: 'operations-p2-restore-key',
        correlationId: (string) Str::uuid(),
    ));
    $backup->forceFill(['status' => BackupStatus::Succeeded])->save();

    Livewire::actingAs($actor)
        ->test(OperationsDashboard::class)
        ->set('restoreBackupId', (string) $backup->id)
        ->set('restoreTargetAlias', 'verify-2026-08-23-modal')
        ->set('restoreEvidenceReference', str_repeat('e', 43))
        ->set('restoreResult', '')
        ->call('recordRestoreVerification')
        ->assertHasErrors('restoreResult')
        ->assertNotDispatched('close-dialog')
        ->set('restoreResult', 'passed')
        ->call('recordRestoreVerification')
        ->assertDispatched('close-dialog', id: 'restore-verification-dialog');
});

it('records backup metadata without claiming a real dump and exposes only safe metadata', function (): void {
    $actor = operationsP2User('backup.run', 'backup.view');
    $backup = app(BackupLifecycleService::class)->request(new BackupRequest(
        actor: $actor,
        artifacts: new BackupArtifactPair(
            database: new BackupArtifact('db-2026-08-09', str_repeat('a', 64), 100),
            media: new BackupArtifact('media-2026-08-09', str_repeat('b', 64), 200),
        ),
        retentionUntil: CarbonImmutable::now()->addDay(),
        operatorKey: 'operations-p2-backup-key',
        correlationId: (string) Str::uuid(),
    ));

    expect($backup->toArray())->not->toHaveKey('operator_key')
        ->and($backup->toArray())->not->toHaveKey('request_payload_hash')
        ->and($backup->toArray())->not->toHaveKey('restore_verification_evidence_reference')
        ->and(AuditLog::query()->where('action', 'operations.backup.requested')->count())->toBe(1);
});

it('executes audit retention only before an explicit cutoff and preserves protected evidence', function (): void {
    $actor = operationsP2User('audit.retention.execute');
    $old = app(AuditLogger::class)->record($actor, 'test.old', $actor, [], [], (string) Str::uuid());
    $protected = app(AuditLogger::class)->record($actor, 'report.export.completed', $actor, [], [], (string) Str::uuid());
    $cutoff = CarbonImmutable::now()->subDay()->startOfDay();
    $oldOccurredAt = $cutoff->subDay();
    DB::table('audit_retention_context')->insert(['token' => 'test-retention-context']);
    DB::table('audit_logs')->where('id', $old->id)->update(['occurred_at' => $oldOccurredAt]);
    DB::table('audit_logs')->where('id', $protected->id)->update(['occurred_at' => $oldOccurredAt]);
    DB::table('audit_retention_context')->where('token', 'test-retention-context')->delete();

    expect(app(AuditRetentionService::class)->execute($actor, $cutoff->toDateString()))->toBe(1)
        ->and(AuditLog::query()->whereKey($old->id)->exists())->toBeFalse()
        ->and(AuditLog::query()->whereKey($protected->id)->exists())->toBeTrue()
        ->and(AuditLog::query()->where('action', 'audit.retention.executed')->count())->toBe(1);
});

it('rolls back maintenance state when the audit write fails', function (): void {
    $actor = operationsP2User('system.maintenance');
    putenv('OPERATIONS_TEST_AUDIT_FAILURE=1');
    $state = new stdClass;
    $state->shouldFail = getenv('OPERATIONS_TEST_AUDIT_FAILURE') !== false;
    DB::listen(function (QueryExecuted $query) use ($state): void {
        if (str_contains(strtolower($query->sql), 'audit_logs') && $state->shouldFail) {
            throw new RuntimeException('audit failure');
        }
    });

    try {
        expect(fn () => app(OperationalSettingsService::class)->setMaintenance($actor, true, 'Scheduled migration'))
            ->toThrow(RuntimeException::class, 'audit failure');
    } finally {
        $state->shouldFail = false;
        putenv('OPERATIONS_TEST_AUDIT_FAILURE');
    }

    expect(App::maintenanceMode()->active())->toBeFalse();
});

it('uses the Laravel maintenance contract and audits state changes without exposing a bypass secret', function (): void {
    $actor = operationsP2User('system.maintenance');
    $service = app(OperationalSettingsService::class);

    $service->setMaintenance($actor, true, 'Scheduled migration');

    expect(App::maintenanceMode()->active())->toBeTrue()
        ->and(App::maintenanceMode()->data()['secret'] ?? null)->toBeNull()
        ->and(AuditLog::query()->pluck('action')->all())->toContain('system.maintenance.changed');

    $service->setMaintenance($actor, false, 'Migration complete');
    expect(App::maintenanceMode()->active())->toBeFalse()
        ->and(AuditLog::query()->where('action', 'system.maintenance.changed')->count())->toBe(2);
});

function operationsP2User(string ...$permissions): User
{
    $user = User::factory()->create();
    $role = Role::query()->create(['name' => 'operations-p2-'.Str::lower(Str::random(8)), 'description' => 'Operations P2 test role']);
    foreach ($permissions as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
        $role->permissions()->attach($permission);
    }
    $user->roles()->attach($role);

    return $user->fresh();
}
