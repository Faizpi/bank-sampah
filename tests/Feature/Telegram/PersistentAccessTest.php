<?php

declare(strict_types=1);

use App\Domain\Deposits\Models\Deposit;
use App\Domain\Identity\Enums\UserStatus;
use App\Domain\Identity\Models\StaffServiceArea;
use App\Domain\Ledger\Models\LedgerEntry;
use App\Domain\Pickups\Models\PickupRequest;
use App\Domain\Telegram\Models\TelegramLink;
use App\Domain\Telegram\Services\TelegramAccess;
use App\Domain\Telegram\Services\TelegramLinkService;
use App\Livewire\Profile\TelegramSettings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Fixtures\WorkflowFixture;
use Tests\Support\TelegramHarness as Bot;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    $this->seed(WorkflowFixture::class);
    Bot::configure();
    $this->citizen = User::where('username', 'warga')->firstOrFail();
    $this->officer = User::where('username', 'petugas')->firstOrFail();
    $this->link = Bot::link($this->citizen, '10001');
});

it('ignores legacy authorization and activity timestamps on a still valid link', function (): void {
    DB::table('telegram_links')->where('id', $this->link->id)->update(['authorized_until' => now()->subYear(), 'last_activity_at' => now()->subYear()]);
    $generation = $this->link->generation;

    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    expect(Bot::lastText('10001'))->toContain('Pilih tanggal');
    expect($this->link->fresh()->generation)->toBe($generation);
});

it('allows expired conversation buttons to be replaced through the bot without web verification', function (): void {
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    $old = Bot::button('10001', 'Hari ini');
    $this->travel(400)->days();

    Bot::update('10001', ['callback' => $old, 'callback_id' => 'expired-button']);
    expect(PickupRequest::count())->toBe(0);
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    Bot::click('10001', 'Hari ini');
    expect(Bot::lastText('10001'))->toContain('alamat');
    expect(TelegramLink::whereKey($this->link->id)->exists())->toBeTrue();
});

it('explains unlinking for keluar instead of silently expiring account access', function (): void {
    $generation = $this->link->generation;
    Bot::text('10001', '/keluar');
    expect(Bot::lastText('10001'))->toContain('Putuskan Telegram', 'akun tetap terhubung')->not->toContain('Otorisasi tindakan diakhiri');
    Bot::text('10001', '/menu');
    Bot::click('10001', 'Jemput sampah');
    expect(Bot::lastText('10001'))->toContain('Pilih tanggal');
    expect($this->link->fresh()->generation)->toBe($generation);
});

it('rejects revoked links even when a stale link model is supplied', function (): void {
    app(TelegramLinkService::class)->revoke($this->citizen, $this->citizen, 'TelegramOnly#2026');
    expect(fn () => app(TelegramAccess::class)->actor($this->link))->toThrow(AuthorizationException::class);
    Bot::text('10001', '/menu');
    expect(Bot::lastText('10001'))->not->toContain($this->citizen->name);
});

it('rejects a stale link generation after an explicit account verification', function (): void {
    $old = $this->link;
    $next = Bot::link($this->citizen, '10001');
    expect(fn () => app(TelegramAccess::class)->actor($old))->toThrow(AuthorizationException::class);
    expect(app(TelegramAccess::class)->actor($next)->id)->toBe($this->citizen->id);
});

it('does not let persistent linking bypass an inactive account', function (): void {
    $this->citizen->forceFill(['status' => UserStatus::Inactive])->save();
    expect(fn () => app(TelegramAccess::class)->actor($this->link))->toThrow(AuthorizationException::class);
    Bot::text('10001', '/menu');
    expect(Bot::lastText('10001'))->not->toContain($this->citizen->name);
});

it('does not let an indefinitely linked officer use an expired service area assignment', function (): void {
    Bot::link($this->officer, '10002');
    $this->travel(400)->days();
    Bot::text('10002', '/menu');
    Bot::click('10002', 'Catat setoran');
    Bot::text('10002', $this->citizen->customerProfile->customer_number);
    $button = Bot::button('10002', $this->citizen->name.' · '.substr($this->citizen->customerProfile->customer_number, 0, 4).'****'.substr($this->citizen->customerProfile->customer_number, -2));
    StaffServiceArea::where('staff_profile_user_id', $this->officer->id)->update(['active_to' => today()->subDay()->toDateString()]);
    Bot::update('10002', ['callback' => $button, 'callback_id' => 'revoked-area']);
    expect(Deposit::count())->toBe(0)->and(LedgerEntry::count())->toBe(0);
    expect(Bot::lastText('10002'))->toContain('Akses atau status sudah berubah');
});

it('shows persistent account status instead of a renewal deadline on the profile', function (): void {
    $this->actingAs($this->citizen);
    Livewire::test(TelegramSettings::class)->assertSee('Terhubung tanpa batas waktu')->assertDontSee('Otorisasi tindakan hingga')->assertDontSee('Perbarui otorisasi')->assertDontSee('Verifikasi ulang akun');
    $this->citizen->forceFill(['password' => 'ChangedOnly#2026'])->save();
    Livewire::test(TelegramSettings::class)->assertSee('Data keamanan akun berubah')->assertSee('Verifikasi ulang akun');
});
