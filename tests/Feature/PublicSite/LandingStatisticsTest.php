<?php

declare(strict_types=1);

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Statistics\Models\StatisticPublication;
use App\Domain\Statistics\Services\LandingStatistics;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    $this->citizen = User::where('role', 'warga')->firstOrFail();
    $this->officer = User::where('role', 'petugas')->firstOrFail();
    $this->createDeposit = function (int $customer, string $status, string $weight = '10.000', mixed $time = null): Deposit {
        return Deposit::query()->create(['deposit_number' => 'DEP-LND-'.substr((string) str()->uuid(), 0, 20), 'customer_id' => $customer,
            'staff_id' => $this->officer->id, 'method' => 'loket', 'occurred_at' => $time ?? now()->subMinute(),
            'status' => $status, 'total_weight_kg' => $weight, 'total_value' => 15000]);
    };
});

it('shows real empty totals and fixed-role counts without treating Admin or Bendahara as Petugas', function (): void {
    $summary = app(LandingStatistics::class)->summary();
    expect($summary['active_customers'])->toBe(1)->and($summary['active_officers'])->toBe(1)
        ->and($summary['deposit_count'])->toBe(0)->and($summary['total_weight_kg'])->toBe('0.000');
    $this->get('/')->assertOk()->assertSee('Warga aktif')->assertSee('Petugas aktif')->assertDontSee('Driver');
});

it('does not count inactive or expired staff assignments', function (): void {
    $this->officer->staffProfile->serviceAreas()->update(['active_to' => today()->subDay()->toDateString()]);
    expect(app(LandingStatistics::class)->activeOfficers())->toBe(0);
});

it('protects small nonempty groups instead of displaying invented zeroes', function (): void {
    ($this->createDeposit)($this->citizen->id, Deposit::STATUS_FINAL);
    $summary = app(LandingStatistics::class)->summary();
    expect($summary['deposit_count'])->toBeNull()->and($summary['total_weight_kg'])->toBeNull()->and($summary['protected'])->toBeTrue();
});

it('counts only finalized nonreversed past deposits when the privacy threshold is satisfied', function (): void {
    foreach (range(1, 5) as $index) {
        $user = User::factory()->create(['role' => 'warga']);
        ($this->createDeposit)($user->id, $index === 5 ? Deposit::STATUS_CORRECTED : Deposit::STATUS_FINAL);
    }
    ($this->createDeposit)($this->citizen->id, Deposit::STATUS_DRAFT);
    ($this->createDeposit)($this->citizen->id, Deposit::STATUS_REVERSED);
    ($this->createDeposit)($this->citizen->id, Deposit::STATUS_FINAL, '10.000', now()->addDay());
    $summary = app(LandingStatistics::class)->summary();
    expect($summary['deposit_count'])->toBe(5)->and($summary['total_weight_kg'])->toBe('50.000');
});

it('respects an existing Admin publication allowlist or disabled publication', function (): void {
    $publication = StatisticPublication::query()->create(['publication_key' => 'public-dashboard', 'metrics' => ['active_customers'], 'dimensions' => ['period'], 'privacy_threshold' => 5, 'is_active' => true]);
    $summary = app(LandingStatistics::class)->summary();
    expect($summary['active_customers'])->toBe(1)->and($summary['active_officers'])->toBeNull()->and($summary['deposit_count'])->toBeNull();
    $publication->update(['is_active' => false]);
    $summary = app(LandingStatistics::class)->summary();
    expect($summary['active_customers'])->toBeNull()->and($summary['total_weight_kg'])->toBeNull();
});
