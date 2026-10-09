<?php

declare(strict_types=1);

use App\Domain\CustomersRegions\Contracts\QrToken;
use App\Domain\Identity\Models\CustomerProfile;
use App\Models\User;
use App\Support\Auth\DemoCredentials;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Fixtures\MasterDataPayload;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->sourcePath = storage_path('app/master-seed.json');
    config()->set('app.master_seed_path', $this->sourcePath);
    $this->payload = MasterDataPayload::make();
    file_put_contents($this->sourcePath, json_encode($this->payload, JSON_THROW_ON_ERROR));
});

it('seeds private master data with preserved hashes new usable QR and no historical transactions', function (): void {
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(5)->and(User::where('role', 'admin')->count())->toBe(2);
    expect(DB::table('access_role_locks')->value('active_admin_count'))->toBe(2);
    foreach (User::all() as $user) {
        expect(Hash::check('PrivateOnly#2026', $user->password))->toBeTrue();
    }
    $profile = CustomerProfile::sole();
    expect(QrToken::fromValue($profile->qr_token_encrypted)->hash())->toBe($profile->qr_token_hash);
    foreach (['deposits', 'ledger_entries', 'balance_holds', 'withdrawal_requests', 'grocery_redemptions', 'pickup_requests', 'sessions', 'notifications'] as $table) {
        expect(DB::table($table)->count())->toBe(0);
    }
    expect(DB::table('staff_service_areas')->count())->toBe(2)
        ->and(DB::table('waste_prices')->value('price'))->toBe(1500);
});

it('refuses repeated seeding without overwriting credentials or QR', function (): void {
    $this->seed(DatabaseSeeder::class);
    $hash = User::first()->password;
    $qr = CustomerProfile::sole()->qr_token_hash;
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(LogicException::class);
    expect(User::first()->password)->toBe($hash)->and(CustomerProfile::sole()->qr_token_hash)->toBe($qr);
});

it('fails with no mutations when the private dataset is missing', function (): void {
    config()->set('app.master_seed_path', storage_path('app/missing.json'));
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(RuntimeException::class);
    expect(User::count())->toBe(0);
});

it('rejects malformed unsafe or inconsistent data before insertion', function (Closure $mutate): void {
    $mutate($this->payload);
    file_put_contents($this->sourcePath, json_encode($this->payload, JSON_THROW_ON_ERROR));
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(Exception::class);
    expect(User::count())->toBe(0)->and(DB::table('dusun')->count())->toBe(0);
})->with([
    'transaction table' => [fn (array &$data) => $data['tables']['ledger_entries'] = []],
    'old QR ciphertext' => [fn (array &$data) => $data['tables']['customer_profiles'][0]['qr_token_encrypted'] = 'old-key-ciphertext'],
    'nested value' => [fn (array &$data) => $data['tables']['users'][0]['name'] = ['unsafe']],
    'old superadmin role' => [fn (array &$data) => $data['tables']['users'][4]['role'] = 'superadmin'],
    'plaintext password' => [fn (array &$data) => $data['tables']['users'][0]['password'] = 'PlaintextPassword'],
    'dangling RT' => [fn (array &$data) => $data['tables']['customer_profiles'][0]['rt_id'] = 99],
    'duplicate assignments' => [fn (array &$data) => $data['tables']['service_area_rt'][] = $data['tables']['service_area_rt'][0]],
    'wrong staff profile' => [fn (array &$data) => $data['tables']['staff_profiles'][0]['user_id'] = 4],
    'invalid price period' => [fn (array &$data) => $data['tables']['waste_prices'][0]['effective_to'] = '2025-01-01 00:00:00'],
    'non-admin price author' => [fn (array &$data) => $data['tables']['waste_prices'][0]['created_by'] = 2],
]);

it('never uses the private dataset or elevates an existing user in production bootstrap', function (): void {
    $citizen = User::factory()->create(['username' => 'bootstrap.user', 'role' => 'warga']);
    config()->set(['app.env' => 'production', 'app.initial_admin_username' => $citizen->username, 'app.initial_admin_password' => 'BootstrapOnly#2026']);
    expect(fn () => $this->seed(DatabaseSeeder::class))->toThrow(LogicException::class);
    expect($citizen->fresh()->hasRole('warga'))->toBeTrue()->and(User::count())->toBe(1);
});

it('production bootstrap is idempotent and ignores local source data', function (): void {
    config()->set(['app.env' => 'production', 'app.initial_admin_username' => 'initial.admin', 'app.initial_admin_password' => 'BootstrapOnly#2026']);
    $this->seed(DatabaseSeeder::class);
    $this->seed(DatabaseSeeder::class);
    expect(User::count())->toBe(1)->and(User::sole()->hasRole('admin'))->toBeTrue();
});

it('hides synthetic quick-login credentials when they do not match the imported accounts', function (): void {
    User::factory()->create(['username' => 'admin', 'role' => 'admin', 'password' => 'PrivateOnly#2026']);
    config()->set('app.demo_mode', true);
    expect(DemoCredentials::available('admin'))->toBeFalse();
    config()->set('app.env', 'production');
    expect(DemoCredentials::available('admin'))->toBeFalse();
});

it('refuses synthetic workflow fixtures outside the isolated testing environment', function (): void {
    config()->set('app.env', 'local');
    expect(fn () => $this->seed(WorkflowFixture::class))->toThrow(LogicException::class);
    expect(User::count())->toBe(0);
});
