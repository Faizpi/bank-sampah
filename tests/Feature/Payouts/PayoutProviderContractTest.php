<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerAccount;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Payouts\Models\SandboxPayout;
use App\Domain\Payouts\Services\PayoutApiFailure;
use App\Domain\Payouts\Services\PayoutService;
use App\Domain\Payouts\Services\PayoutWorker;
use App\Livewire\Citizen\PayoutShow;
use App\Livewire\Treasurer\PayoutMonitor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\PayoutHarness as Payout;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Payout::configure();
    $this->owner = User::where('username', 'warga')->firstOrFail();
    Payout::credit($this->owner, User::where('username', 'petugas')->firstOrFail());
});

it('sends the required domestic recipient address without copying private profile data', function (string $channel): void {
    $this->owner->customerProfile->forceFill(['address' => 'PRIVATE HOME ADDRESS'])->save();
    $row = app(PayoutService::class)->request($this->owner, Payout::request($channel), (string) str()->uuid());
    Http::fake(function ($request) use ($row) {
        // Independent required-field assertions from the documented v3 contract.
        foreach (['type', 'relationship', 'account_details', 'address', 'given_name', 'surname'] as $field) {
            expect($request['recipient'])->toHaveKey($field);
        }
        expect($request['recipient']['address'])->toHaveKeys(['country', 'street_line_1', 'city', 'province_state', 'postal_code']);
        foreach (['street_line_1', 'city', 'province_state', 'postal_code'] as $field) {
            expect($request['recipient']['address'][$field])->toBeString()->not->toBe('');
        }
        expect($request['recipient']['address']['country'])->toBe('ID')
            ->and($request['recipient']['address']['postal_code'])->toMatch('/^[0-9]{5}$/D')
            ->and($request['payout_details'])->toBe(['source_currency' => 'IDR', 'source_amount' => 20000, 'destination_currency' => 'IDR'])
            ->and(json_encode($request->data()))->not->toContain('PRIVATE HOME ADDRESS');

        return Http::response(Payout::response($row));
    });
    expect(app(PayoutWorker::class)->run())->toBe(1)->and($row->fresh()->status)->toBe('succeeded');
    Http::assertSentCount(1);
})->with(['bca', 'mandiri', 'bri', 'bni', 'dana', 'gopay']);

it('releases a documented first submission rejection once without a debit or automatic retry', function (int $status, string $errorCode): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(['error_code' => $errorCode, 'message' => 'DO NOT STORE RAW MESSAGE'], $status)]);
    app(PayoutWorker::class)->run();
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('failed')->and($row->fresh()->attempts)->toBe(1)
        ->and($row->fresh()->failure_code)->toBe('payout.http_'.$status.':'.$errorCode)
        ->and($row->fresh()->lease)->toBeNull()->and($row->fresh()->next_check_at)->toBeNull()
        ->and($row->fresh()->balanceHold->status)->toBe('dilepas')
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000)->and(LedgerEntry::count())->toBe(1);
    Http::assertSentCount(1);
    $this->assertDatabaseHas('audit_logs', ['action' => 'payout.submission_rejected', 'actor_type' => 'system']);
})->with([[400, 'API_VALIDATION_ERROR'], [400, 'INVALID_VALUE_ERROR'], [401, 'INVALID_API_KEY'], [403, 'REQUEST_FORBIDDEN_ERROR'], [404, 'DATA_NOT_FOUND']]);

it('keeps unclassified errors and ambiguous responses held for reconciliation', function (int $status, mixed $body): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response($body, $status)]);
    app(PayoutWorker::class)->run();
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->balanceHold->status)->toBe('aktif')
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(55000)->and(LedgerEntry::count())->toBe(1);
    Http::assertSentCount(1);
})->with([
    [400, ['error_code' => 'UNKNOWN_ERROR', 'message' => 'unknown']],
    [400, 'not json'],
    [401, ['message' => 'no documented error code']],
    [409, ['error_code' => 'DUPLICATE_ERROR']],
    [429, ['error_code' => 'API_VALIDATION_ERROR']],
    [500, ['error_code' => 'SERVER_ERROR']],
    [200, 'not json'],
]);

it('does not release a previous unknown submission when a recovery POST is rejected', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(static fn () => throw new ConnectionException('timeout'));
    app(PayoutWorker::class)->run();
    $this->travel(2)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response(['error_code' => 'API_VALIDATION_ERROR'], 400)]);
    $this->artisan('payouts:recover', ['id' => $row->id, '--reference' => $row->reference_id])->assertFailed();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->attempts)->toBe(2)
        ->and($row->fresh()->balanceHold->status)->toBe('aktif')->and(LedgerEntry::count())->toBe(1);
    Http::assertSent(fn ($request): bool => $request->data() === $row->request_payload && $request->hasHeader('idempotency-key', $row->provider_key));
});

it('never releases an existing provider payout because its lookup failed', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row, 'ACCEPTED'))]);
    app(PayoutWorker::class)->run();
    $this->travel(2)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response(['error_code' => 'INVALID_API_KEY'], 401)]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->provider_id)->not->toBeNull()
        ->and($row->fresh()->balanceHold->status)->toBe('aktif')->and($row->fresh()->next_check_at)->not->toBeNull();
    Http::assertSent(fn ($request): bool => $request->method() === 'GET');
});

it('defers to reconciliation when a webhook arrives during a rejected creation response', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    Http::fake(function () use ($row) {
        $this->postJson(route('payout.webhook'), [
            'event' => 'v3_payout.succeeded', 'business_id' => 'test-business', 'created' => now()->toIso8601String(),
            'data' => ['payout_id' => 'po-test-'.$row->id, 'reference_id' => $row->reference_id],
        ], ['x-callback-token' => str_repeat('w', 40)])->assertOk();

        return Http::response(['error_code' => 'API_VALIDATION_ERROR'], 400);
    });
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->balanceHold->status)->toBe('aktif');
    $this->travel(6)->minutes();
    Http::swap(new Factory);
    Http::preventStrayRequests();
    Http::fake(['api.xendit.co/*' => Http::response(Payout::response($row))]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->status)->toBe('succeeded')->and(LedgerEntry::count())->toBe(2);
});

it('retains only allowlisted codes and field names in operator diagnostics', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $secret = 'xnd_development_NEVER_DISPLAY_081234567890';
    Http::fake(['api.xendit.co/*' => Http::response([
        'error_code' => 'API_VALIDATION_ERROR', 'message' => $secret,
        'errors' => [['path' => ['recipient', 'address'], 'message' => $secret, 'value' => $secret]],
    ], 400)]);
    app(PayoutWorker::class)->run();
    expect($row->fresh()->failure_code)->toBe('payout.http_400:API_VALIDATION_ERROR:recipient.address');
    $this->actingAs(User::where('username', 'bendahara')->firstOrFail());
    Livewire::test(PayoutMonitor::class)->assertSee('Layanan pencairan belum dapat memproses pengajuan ini.')
        ->assertDontSee('alamat penerima')->assertDontSee('API_VALIDATION_ERROR')->assertDontSee($secret);
    $this->actingAs($this->owner);
    Livewire::test(PayoutShow::class, ['payout' => $row->fresh()])->assertSee('Saldo yang disisihkan sudah tersedia kembali.')
        ->assertDontSee('API_VALIDATION_ERROR')->assertDontSee($secret);
    $this->artisan('payouts:diagnose')->expectsOutputToContain($row->request_number)->expectsOutputToContain('alamat penerima')
        ->expectsOutputToContain('Tidak ada payout baru atau perubahan saldo')->assertSuccessful();
    expect(json_encode(DB::table('audit_logs')->get()))->not->toContain($secret);
});

it('checks an uncertain payout through GET without exposing provider data or changing balances', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request('gopay'), (string) str()->uuid());
    $row->forceFill(['status' => 'uncertain', 'provider_id' => 'po-test-'.$row->id, 'failure_code' => 'payout.response_unconfirmed'])->save();
    $before = $row->fresh()->getAttributes();
    $response = Payout::response($row, 'SUCCEEDED');
    $response['recipient']['account_details']['account_number'] = 'SECRET-NUMBER';
    $response['private_data'] = 'SECRET-PROVIDER-DATA';
    Http::fake(['api.xendit.co/*' => Http::response($response)]);

    expect(Artisan::call('payouts:diagnose'))->toBe(0);
    $output = Artisan::output();
    expect($output)->toContain('Status Xendit: SUCCEEDED', 'recipient.account_details.account_number: tidak cocok')
        ->not->toContain('SECRET-NUMBER', 'SECRET-PROVIDER-DATA')
        ->and($row->fresh()->getAttributes())->toBe($before)
        ->and(LedgerEntry::count())->toBe(1)
        ->and($row->fresh()->balanceHold->status)->toBe('aktif');
    Http::assertSentCount(1);
    Http::assertSent(fn ($request): bool => $request->method() === 'GET' && str_ends_with($request->url(), '/'.$row->provider_id));
});

it('rejects incomplete research address settings before reserving any balance', function (): void {
    config()->set('payouts.test_recipient_address', ['country' => 'ID']);
    expect(fn () => app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid()))
        ->toThrow(ValidationException::class);
    expect(SandboxPayout::count())->toBe(0)->and(LedgerEntry::count())->toBe(1)
        ->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000);
    Http::assertNothingSent();
});

it('never rewrites a saved provider body after research address settings change', function (): void {
    $key = (string) str()->uuid();
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), $key);
    $original = $row->getRawOriginal('request_payload');
    config()->set('payouts.test_recipient_address.street_line_1', 'Jalan Simulasi Berbeda No. 2');
    $replay = app(PayoutService::class)->request($this->owner, Payout::request(), $key);
    expect($replay->id)->toBe($row->id)->and($replay->getRawOriginal('request_payload'))->toBe($original);
    expect($replay->request_payload['recipient']['address']['street_line_1'])->not->toBe('Jalan Simulasi Berbeda No. 2');
    Http::assertNothingSent();
});

it('discards unknown codes and field paths even when they look like identifiers', function (): void {
    $error = PayoutApiFailure::http(400, ['error_code' => 'PRIVATE_081234567890', 'message' => 'secret',
        'errors' => [['path' => ['recipient', 'PRIVATE_081234567890']]],
    ], creating: true);
    expect($error->codeName)->toBe('payout.http_400')->and($error->uncertain)->toBeTrue();
});

it('reads different validation metadata formats while retaining only known field names', function (array $errors, string $field): void {
    $failure = PayoutApiFailure::http(400, ['error_code' => 'API_VALIDATION_ERROR', 'errors' => $errors], creating: true);
    expect($failure->codeName)->toBe('payout.http_400:API_VALIDATION_ERROR:'.$field)
        ->and(strlen($failure->codeName))->toBeLessThanOrEqual(100)->and($failure->uncertain)->toBeFalse();
})->with([
    [[['path' => ['body', 'recipient', 'address', 'country']]], 'recipient.address.country'],
    [[['field' => 'request.body.recipient.account_details.routing_value_1']], 'recipient.account_details.routing_value_1'],
    [[['instancePath' => '/recipient/address', 'params' => ['missingProperty' => 'country']]], 'recipient.address.country'],
    [[['instancePath' => '', 'params' => ['missingProperty' => 'sender']]], 'sender'],
    [[['loc' => ['body', 'recipient', 'surname']]], 'recipient.surname'],
    [[['field_name' => 'sender.business_name']], 'sender.business_name'],
    [['recipient' => ['address' => ['country' => ['required']]]], 'recipient.address.country'],
    [['details' => [['property' => '$.payout_details.source_amount']]], 'payout_details.source_amount'],
    [['recipient.address' => ['message' => 'private', 'value' => 'private']], 'recipient.address'],
    [['"recipient.address" is required'], 'recipient.address'],
    [[['parameter' => 'headers.api-version']], 'api-version'],
]);

it('does not extract private values or unknown property names from validation messages', function (): void {
    $secret = 'xnd_development_PRIVATE_081234567890';
    $failure = PayoutApiFailure::http(400, ['error_code' => 'API_VALIDATION_ERROR', 'errors' => [
        ['message' => '"recipient.address" is required', 'value' => $secret, 'context' => ['path' => 'recipient.surname']],
        ['path' => ['recipient', $secret]],
    ]], creating: true);
    expect($failure->codeName)->toBe('payout.http_400:API_VALIDATION_ERROR')->not->toContain($secret, 'recipient.address', 'recipient.surname');
});

it('checks existing request structure without sending or changing the payout', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request('gopay'), (string) str()->uuid());
    $row->forceFill(['status' => 'failed', 'failure_code' => 'payout.http_400:API_VALIDATION_ERROR', 'attempts' => 1])->save();
    $snapshot = $row->getRawOriginal('request_payload');
    $this->artisan('payouts:diagnose')->expectsOutputToContain('[OK] Struktur inti request tersimpan')
        ->expectsOutputToContain('Nama field penolakan lama belum tersimpan')->assertSuccessful();
    expect($row->fresh()->getRawOriginal('request_payload'))->toBe($snapshot)
        ->and($row->fresh()->status)->toBe('failed')->and(LedgerEntry::count())->toBe(1);
    Http::assertNothingSent();
});

it('identifies a missing address in a legacy snapshot without modifying that snapshot', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request('bca'), (string) str()->uuid());
    // Create a separate legacy-shaped fixture rather than bypassing identity protection.
    $legacy = $row->replicate(['balance_hold_id']);
    $payload = $row->request_payload;
    $reference = (string) str()->uuid();
    unset($payload['recipient']['address']);
    $payload['reference_id'] = $reference;
    $legacy->forceFill(['request_number' => 'SIM-20261113120000-LEGACY01', 'reference_id' => $reference,
        'provider_key' => (string) str()->uuid(), 'request_payload' => $payload, 'status' => 'failed',
        'failure_code' => 'payout.http_400:API_VALIDATION_ERROR', 'attempts' => 1])->save();
    $before = $legacy->getRawOriginal('request_payload');
    $this->artisan('payouts:diagnose')->expectsOutputToContain('Isian request tersimpan perlu diperiksa: recipient.address.country')
        ->assertSuccessful();
    expect($legacy->fresh()->getRawOriginal('request_payload'))->toBe($before)
        ->and($legacy->fresh()->provider_id)->toBeNull()->and(LedgerEntry::count())->toBe(1);
    Http::assertNothingSent();
});

it('closes only a confirmed legacy first attempt HTTP 400 without changing its immutable request', function (): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $row->forceFill(['status' => 'uncertain', 'attempts' => 1, 'failure_code' => 'payout.http_400', 'next_check_at' => null])->save();
    $original = $row->getRawOriginal('request_payload');
    $this->artisan('payouts:resolve-rejected', ['request' => $row->request_number])->assertFailed();
    expect($row->fresh()->balanceHold->status)->toBe('aktif');
    $parameters = ['request' => $row->request_number, '--confirm-not-created' => true];
    $this->artisan('payouts:resolve-rejected', $parameters)->assertSuccessful();
    $this->artisan('payouts:resolve-rejected', $parameters)->assertSuccessful();
    expect($row->fresh()->status)->toBe('failed')->and($row->fresh()->getRawOriginal('request_payload'))->toBe($original)
        ->and($row->fresh()->balanceHold->status)->toBe('dilepas')->and(LedgerEntry::count())->toBe(1)
        ->and(SandboxPayout::count())->toBe(1)->and(LedgerAccount::firstOrFail()->availableBalance())->toBe(75000)
        ->and(DB::table('audit_logs')->where('action', 'payout.submission_rejected')->count())->toBe(1);
    Http::assertNothingSent();
});

it('refuses legacy closure for any ambiguous or submitted request', function (string $case): void {
    $row = app(PayoutService::class)->request($this->owner, Payout::request(), (string) str()->uuid());
    $row->forceFill(['status' => 'uncertain', 'attempts' => 1, 'failure_code' => 'payout.http_400', 'next_check_at' => null])->save();
    $row->forceFill(match ($case) {
        'timeout' => ['failure_code' => 'payout.transport'],
        'retry' => ['attempts' => 2],
        'provider' => ['provider_id' => 'po-existing'],
        'business' => ['business_id' => 'test-business'],
        'lease' => ['lease' => (string) str()->uuid(), 'leased_at' => now()],
        default => [],
    })->save();
    if ($case === 'webhook') {
        DB::table('sandbox_payout_events')->insert(['sandbox_payout_id' => $row->id, 'dedupe_key' => str_repeat('d', 64),
            'event' => 'v3_payout.succeeded', 'provider_id' => 'po-existing', 'created_at' => now()]);
    }
    $this->artisan('payouts:resolve-rejected', ['request' => $row->request_number, '--confirm-not-created' => true])->assertFailed();
    expect($row->fresh()->status)->toBe('uncertain')->and($row->fresh()->balanceHold->status)->toBe('aktif')
        ->and(LedgerEntry::count())->toBe(1);
    Http::assertNothingSent();
})->with(['timeout', 'retry', 'provider', 'business', 'lease', 'webhook']);
