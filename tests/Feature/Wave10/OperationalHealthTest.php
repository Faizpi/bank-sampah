<?php

declare(strict_types=1);

use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Operations\Enums\BackupRestoreVerificationResult;
use App\Domain\Operations\Enums\BackupStatus;
use App\Domain\Operations\Models\BackupLog;
use App\Domain\Operations\Services\OperationalHealthService;
use App\Domain\Operations\Services\SchedulerHeartbeat;
use App\Http\Middleware\EnsureSessionIsFresh;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Cache::forget(SchedulerHeartbeat::EXPIRE_KEY);
    Cache::forget(SchedulerHeartbeat::PURGE_KEY);
});

it('preserves the anonymous liveness contract without operational internals', function (): void {
    $response = $this->get('/health');

    $response
        ->assertOk()
        ->assertExactJson(['status' => 'ok'])
        ->assertHeader('X-Content-Type-Options', 'nosniff');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('no-store')
        ->toContain('no-cache')
        ->and((string) $response->getContent())
        ->not->toContain('database')
        ->not->toContain('storage');
});

it('keeps operational health private behind auth, fresh session, and system maintenance permission', function (): void {
    $this->get('/operations/health')
        ->assertRedirect(route('login'));

    $user = User::factory()->create();
    $this->actingAs($user)
        ->get('/operations/health')
        ->assertForbidden();

    grantOperationalHealthPermission($user);
    $this->withSession([
        EnsureSessionIsFresh::LAST_ACTIVITY_KEY => now()->subMinutes(31)->getTimestamp(),
    ])->actingAs($user)
        ->get('/operations/health')
        ->assertRedirect(route('login'));
});

it('returns a sanitized healthy operational contract when both scheduler heartbeats are fresh', function (): void {
    $user = User::factory()->create();
    grantOperationalHealthPermission($user);
    operationalVerifiedBackup();
    app(SchedulerHeartbeat::class)->record(SchedulerHeartbeat::EXPIRE_KEY);
    app(SchedulerHeartbeat::class)->record(SchedulerHeartbeat::PURGE_KEY);

    $before = BackupLog::query()->count();
    $response = $this->actingAs($user)->get('/operations/health');

    $response
        ->assertOk()
        ->assertJsonPath('status', 'ok')
        ->assertJsonPath('checks.database.status', 'ok')
        ->assertJsonPath('checks.private_storage.status', 'ok')
        ->assertJsonPath('checks.scheduler.status', 'ok')
        ->assertJsonPath('checks.scheduler.topology', 'cron')
        ->assertJsonPath('checks.queue.status', 'not_applicable')
        ->assertJsonPath('checks.verified_backup.status', 'ok')
        ->assertJsonPath('checks.verified_backup.verified_at', 'recent');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    expect($response->getContent())
        ->not->toContain(base_path())
        ->not->toContain(storage_path())
        ->not->toContain('backup-db-')
        ->not->toContain(str_repeat('a', 64))
        ->not->toContain('exception')
        ->and(BackupLog::query()->count())->toBe($before);
});

it('reports a missing scheduler heartbeat as degraded without exposing cache data', function (): void {
    $result = app(OperationalHealthService::class)->check()->toArray();

    expect($result['scheduler'])
        ->toBe(['status' => 'degraded', 'reason' => 'scheduler_heartbeat_missing'])
        ->and(json_encode($result['scheduler'], JSON_THROW_ON_ERROR))
        ->not->toContain((string) now()->getTimestamp());
});

it('reports a stale scheduler heartbeat as degraded without exposing timestamps', function (): void {
    config()->set('operations.scheduler.heartbeat_freshness_seconds', 3600);
    Cache::put(SchedulerHeartbeat::EXPIRE_KEY, now()->subHours(2)->getTimestamp(), 172800);
    app(SchedulerHeartbeat::class)->record(SchedulerHeartbeat::PURGE_KEY);

    $result = app(OperationalHealthService::class)->check()->toArray();

    expect($result['scheduler'])
        ->toBe(['status' => 'degraded', 'reason' => 'scheduler_heartbeat_stale'])
        ->and(json_encode($result['scheduler'], JSON_THROW_ON_ERROR))
        ->not->toContain((string) now()->subHours(2)->getTimestamp());
});

it('returns 503 with sanitized reason codes when a required check is degraded', function (): void {
    $user = User::factory()->create();
    grantOperationalHealthPermission($user);
    config()->set('filesystems.disks.media_private.root', public_path());

    $response = $this->actingAs($user)->get('/operations/health');

    $response
        ->assertStatus(503)
        ->assertJsonPath('status', 'degraded')
        ->assertJsonPath('checks.private_storage.status', 'degraded')
        ->assertJsonPath('checks.private_storage.reason', 'private_storage_unavailable');

    expect((string) $response->headers->get('Cache-Control'))
        ->toContain('private')
        ->toContain('no-store');

    expect($response->getContent())
        ->not->toContain(public_path())
        ->not->toContain(storage_path())
        ->not->toContain('database.sqlite');
});

it('checks database queue applicability without exposing queue payloads', function (): void {
    $user = User::factory()->create();
    grantOperationalHealthPermission($user);
    operationalVerifiedBackup();
    app(SchedulerHeartbeat::class)->record(SchedulerHeartbeat::EXPIRE_KEY);
    app(SchedulerHeartbeat::class)->record(SchedulerHeartbeat::PURGE_KEY);
    config()->set([
        'queue.default' => 'database',
        'operations.queue.worker_mode' => 'oneshot',
    ]);
    Schedule::command('queue:work database --stop-when-empty')
        ->everyMinute()
        ->timezone('Asia/Jakarta');

    $response = $this->actingAs($user)->get('/operations/health');

    $response
        ->assertOk()
        ->assertJsonPath('checks.queue.status', 'ok')
        ->assertJsonPath('checks.queue.mode', 'database')
        ->assertJsonPath('checks.scheduler.status', 'ok');

    expect($response->getContent())
        ->not->toContain('payload')
        ->not->toContain('jobs');
});

function grantOperationalHealthPermission(User $user): void
{
    $role = Role::query()->create([
        'name' => 'operational-health-'.$user->id.'-'.Str::lower(Str::random(8)),
        'description' => 'Operational health test role',
    ]);
    $permission = Permission::query()->firstOrCreate([
        'name' => 'system.maintenance',
    ], [
        'description' => 'System maintenance test permission',
    ]);
    $role->permissions()->attach($permission, ['reason' => 'Operational health test']);
    $user->roles()->attach($role, ['reason' => 'Operational health test']);
}

it('fails closed for null or invalid restore evidence in health eligibility', function (mixed $evidenceReference): void {
    $backup = operationalVerifiedBackup();
    $backup->forceFill(['restore_verification_evidence_reference' => $evidenceReference])->save();

    $result = app(OperationalHealthService::class)->check()->toArray();

    expect($result['verified_backup'])->toBe([
        'status' => 'degraded',
        'reason' => 'verified_backup_stale_or_missing',
    ]);
})->with([
    'null evidence' => null,
    'invalid evidence' => 'invalid-evidence-reference',
]);

function operationalVerifiedBackup(): BackupLog
{
    $now = CarbonImmutable::now();

    return BackupLog::query()->create([
        'backup_pair_uuid' => (string) Str::uuid(),
        'initiated_by' => User::factory()->create()->id,
        'status' => BackupStatus::Succeeded,
        'database_location_alias' => 'backup-db-operational-test',
        'media_location_alias' => 'backup-media-operational-test',
        'database_sha256' => str_repeat('a', 64),
        'media_sha256' => str_repeat('b', 64),
        'database_size_bytes' => 1200,
        'media_size_bytes' => 3400,
        'retention_until' => $now->addDay(),
        'started_at' => $now->subMinute(),
        'finished_at' => $now,
        'restore_tested_at' => $now,
        'restore_verification_result' => BackupRestoreVerificationResult::Passed,
        'restore_verification_target_alias' => 'verify-operational-test',
        'restore_verification_evidence_reference' => str_repeat('e', 43),
    ]);
}
