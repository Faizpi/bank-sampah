<?php

declare(strict_types=1);

use App\Domain\AuditReconciliation\Services\AuditLogger;
use App\Domain\CustomersRegions\Models\Dusun;
use App\Domain\CustomersRegions\Models\Rt;
use App\Domain\CustomersRegions\Models\Rw;
use App\Domain\CustomersRegions\Models\ServiceArea;
use App\Domain\Identity\Models\Permission;
use App\Domain\Identity\Models\Role;
use App\Domain\Pickups\Enums\PickupStatus;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Platform\Models\Media;
use App\Domain\Reports\Services\ReportExportService;
use App\Http\Middleware\ApplyResponseSecurityHeaders;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Symfony\Component\HttpFoundation\Response;

uses(RefreshDatabase::class);

it('applies response security headers without an unsafe CSP and limits HSTS to secure production requests', function (): void {
    config()->set(['app.env' => 'production', 'app.debug' => false]);

    $response = $this->get('/');
    $response
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
        ->assertHeader('Content-Security-Policy', "base-uri 'self'; form-action 'self'; frame-ancestors 'none'")
        ->assertHeader('Permissions-Policy', 'accelerometer=(), autoplay=(), camera=(), display-capture=(), geolocation=(), gyroscope=(), magnetometer=(), microphone=(), payment=(), usb=()');
    expect((string) $response->headers->get('Content-Security-Policy'))->not->toContain('unsafe-inline');

    $secureResponse = ApplyResponseSecurityHeaders::apply(Request::create('https://bank-sampah.test/'), new Response);
    expect($secureResponse->headers->get('Strict-Transport-Security'))->toBe('max-age=31536000; includeSubDomains');

    config()->set('app.env', 'local');
    $insecureResponse = ApplyResponseSecurityHeaders::apply(Request::create('http://bank-sampah.test/'), new Response);
    expect($insecureResponse->headers->get('Strict-Transport-Security'))->toBeNull();
});

it('prevents caching for authentication and authenticated private responses', function (): void {
    $loginResponse = $this->get(route('login'));
    $loginResponse->assertOk();
    expect((string) $loginResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    $privateResponse = $this->actingAs(User::factory()->create())->get(route('citizen.dashboard'));
    $privateResponse->assertForbidden();
    expect((string) $privateResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    $cameraDisabled = $this->actingAs(User::factory()->create())->get(route('citizen.customer-card'));
    $cameraDisabled->assertForbidden();
    expect((string) $cameraDisabled->headers->get('Permissions-Policy'))->toContain('camera=()');
});

it('keeps private media IDOR hidden and omits storage paths from headers and error bodies', function (): void {
    Storage::fake('media_private');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    grantWave10SecurityPermissions($owner, 'pickup.view');
    grantWave10SecurityPermissions($other, 'pickup.view');
    $pickup = wave10PickupFor($owner);
    $path = 'private/receipts/'.Str::uuid().'-storage-secret.pdf';
    Storage::disk('media_private')->put($path, 'private-proof');
    $media = Media::query()->create([
        'uuid' => (string) Str::uuid(),
        'disk' => 'media_private',
        'path' => $path,
        'original_name' => 'receipt.pdf',
        'mime_type' => 'application/pdf',
        'size' => 13,
        'checksum' => hash('sha256', 'private-proof'),
        'visibility' => 'private',
        'uploader_id' => $owner->id,
        'attachable_type' => PickupRequest::class,
        'attachable_id' => $pickup->id,
    ]);

    $ownerResponse = $this->actingAs($owner)->get(route('pickup.media', $media));
    $ownerResponse
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect((string) $ownerResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    config()->set('app.debug', false);
    $otherResponse = $this->actingAs($other)->get(route('pickup.media', $media));

    $otherResponse->assertNotFound();
    expect((string) $otherResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $responseText = (string) $otherResponse->getContent().'|'.json_encode($otherResponse->headers->all(), JSON_THROW_ON_ERROR);
    expect($responseText)
        ->not->toContain($path)
        ->not->toContain('storage-secret')
        ->not->toContain(storage_path('app/media'));
});

it('keeps export IDOR hidden and applies private response controls to downloads and failures', function (): void {
    Storage::fake('media_private');
    $owner = User::factory()->create();
    $other = User::factory()->create();
    grantWave10SecurityPermissions($owner, 'report.view', 'report.export');
    grantWave10SecurityPermissions($other, 'report.view', 'report.export');
    $export = app(ReportExportService::class)->export($owner, 'deposits', [
        'start' => '2026-08-01',
        'end' => '2026-08-02',
    ], 'csv');

    $ownerResponse = $this->actingAs($owner)->get(route('reports.export.download', $export));
    $ownerResponse
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff');
    expect((string) $ownerResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');

    config()->set('app.debug', false);
    $otherResponse = $this->actingAs($other)->get(route('reports.export.download', $export));

    $otherResponse->assertNotFound();
    expect((string) $otherResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
    $responseText = (string) $otherResponse->getContent().'|'.json_encode($otherResponse->headers->all(), JSON_THROW_ON_ERROR);
    expect($responseText)
        ->not->toContain((string) $export->path)
        ->not->toContain(storage_path('app/media'));
});

it('enforces the public QR rate limit and wires every critical named limiter centrally', function (): void {
    config()->set('app.public_qr_max_attempts_per_minute', 1);
    $token = str_repeat('a', 43);

    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.61'])
        ->get(route('public.deposit-verification', ['token' => $token]))
        ->assertNotFound();
    $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.61'])
        ->get(route('public.deposit-verification', ['token' => $token]))
        ->assertStatus(429);

    expect(app('router')->getRoutes()->getByName('public.prices')?->gatherMiddleware())->toContain('throttle:public-data');
    expect(app('router')->getRoutes()->getByName('public.programs')?->gatherMiddleware())->toContain('throttle:public-data');
    expect(app('router')->getRoutes()->getByName('reports.export.download')?->gatherMiddleware())->toContain('throttle:exports');
    expect(app('router')->getRoutes()->getByName('citizen.withdrawal.create')?->gatherMiddleware())->toContain('throttle:financial');
    expect(app('router')->getRoutes()->getByName('officer.deposit-form')?->gatherMiddleware())->toContain('throttle:financial');
    expect(config('livewire.temporary_file_upload.middleware'))->toBe('throttle:uploads');
    expect(Livewire::getPersistentMiddleware())->toContain(ThrottleRequests::class);
});

it('throttles private export downloads without bypassing authorization or private cache controls', function (): void {
    Storage::fake('media_private');
    config()->set('app.export_max_attempts_per_minute', 1);
    $owner = User::factory()->create();
    grantWave10SecurityPermissions($owner, 'report.view', 'report.export');
    $export = app(ReportExportService::class)->export($owner, 'deposits', [
        'start' => '2026-08-01',
        'end' => '2026-08-02',
    ], 'csv');

    $this->actingAs($owner)->get(route('reports.export.download', $export))->assertOk();

    $limitedResponse = $this->actingAs($owner)->get(route('reports.export.download', $export));
    $limitedResponse->assertStatus(429);
    expect((string) $limitedResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('throttles financial request routes before critical Livewire actions can run', function (): void {
    config()->set('app.financial_request_max_attempts_per_minute', 1);
    $actor = User::factory()->create();
    grantWave10SecurityPermissions($actor, 'withdrawal.request');

    $this->actingAs($actor)->get(route('citizen.withdrawal.create'))->assertOk();

    $limitedResponse = $this->actingAs($actor)->get(route('citizen.withdrawal.create'));
    $limitedResponse->assertStatus(429);
    expect((string) $limitedResponse->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('sanitizes secret-bearing storage metadata before it reaches an audit record', function (): void {
    $actor = User::factory()->create();
    $correlationId = (string) Str::uuid();
    $evidenceReference = str_repeat('e', 43);
    $audit = app(AuditLogger::class)->record($actor, 'wave10.security.checked', $actor, [], [
        'storage_path' => storage_path('app/media/private-proof.pdf'),
        'export_filename' => 'laporan-secret.csv',
        'signed_url' => 'https://bank-sampah.test/media?signature=secret',
        'restore_verification_evidence_reference' => $evidenceReference,
        'backup_pair_uuid' => $correlationId,
        'database_sha256' => str_repeat('a', 64),
        'nested' => ['proof_path' => 'private/proof.pdf'],
    ], $correlationId);

    expect($audit->getAttribute('new_values'))->toBe([
        'storage_path' => '[REDACTED]',
        'export_filename' => '[REDACTED]',
        'signed_url' => '[REDACTED]',
        'restore_verification_evidence_reference' => '[REDACTED]',
        'backup_pair_uuid' => $correlationId,
        'database_sha256' => str_repeat('a', 64),
        'nested' => ['proof_path' => '[REDACTED]'],
    ]);
});

function grantWave10SecurityPermissions(User $user, string ...$permissions): void
{
    $role = Role::query()->create([
        'name' => 'wave10-security-'.$user->id.'-'.Str::lower(Str::random(8)),
        'description' => 'Wave10 security test role',
    ]);

    foreach ($permissions as $permissionName) {
        $permission = Permission::query()->firstOrCreate(['name' => $permissionName], ['description' => $permissionName]);
        $role->permissions()->syncWithoutDetaching([$permission->id => ['reason' => 'Wave10 security test']]);
    }

    $user->roles()->syncWithoutDetaching([$role->id => ['reason' => 'Wave10 security test']]);
}

function wave10PickupFor(User $owner): PickupRequest
{
    $dusun = Dusun::query()->create(['code' => 'W10-DS-'.$owner->id, 'name' => 'Dusun Wave10', 'is_active' => true]);
    $rw = Rw::query()->create(['dusun_id' => $dusun->id, 'code' => 'W10-RW-'.$owner->id, 'name' => 'RW Wave10', 'is_active' => true]);
    $rt = Rt::query()->create(['rw_id' => $rw->id, 'code' => 'W10-RT-'.$owner->id, 'name' => 'RT Wave10', 'is_active' => true]);
    $area = ServiceArea::query()->create(['name' => 'Area Wave10 '.$owner->id, 'is_active' => true]);

    return PickupRequest::query()->create([
        'request_number' => 'W10-PUP-'.$owner->id,
        'customer_id' => $owner->id,
        'rt_id' => $rt->id,
        'service_area_id' => $area->id,
        'address' => 'Alamat private media Wave10',
        'selected_date' => today()->addDay(),
        'status' => PickupStatus::PendingReview,
    ]);
}
