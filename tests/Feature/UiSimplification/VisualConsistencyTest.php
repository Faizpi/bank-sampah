<?php

declare(strict_types=1);

use App\Domain\Ledger\Models\LedgerEntry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\Fixtures\WorkflowFixture;

uses(RefreshDatabase::class);

it('keeps six citizen services discoverable through labelled icon cards', function (): void {
    $this->seed(WorkflowFixture::class);
    $response = $this->actingAs(User::where('username', 'warga')->firstOrFail())->get('/warga/layanan');
    $response->assertOk()->assertSee('data-page-heading', false)->assertDontSee('persetujuan Admin');
    expect(substr_count($response->getContent(), 'data-action-card'))->toBe(6);
    expect(substr_count($response->getContent(), '<h1 '))->toBe(1);
    foreach (['citizen.pickup.create', 'citizen.withdrawal.create', 'citizen.grocery.create', 'citizen.estimate', 'public.mobile-schedule'] as $route) {
        $response->assertSee(route($route), false);
    }
});

it('uses the same heading and interactive actions for the two staff dashboards', function (): void {
    $this->seed(WorkflowFixture::class);
    foreach (['petugas', 'bendahara'] as $role) {
        $response = $this->actingAs(User::where('username', $role)->firstOrFail())->get('/dashboard/'.$role);
        $response->assertOk()->assertSee('data-page-heading', false)->assertSee('data-action-card', false)
            ->assertDontSee('persetujuan Admin')->assertDontSee('catat bukti pembayaran');
        expect(substr_count($response->getContent(), '<h1 '))->toBe(1);
    }
});

it('keeps service queues readable without hiding handover safeguards', function (): void {
    $this->seed(WorkflowFixture::class);
    foreach (['petugas' => '/petugas/sembako', 'bendahara' => '/bendahara/pencairan'] as $role => $path) {
        $this->actingAs(User::where('username', $role)->firstOrFail())->get($path)->assertOk()
            ->assertSee('data-page-heading', false)->assertSee('placeholder="Contoh: nama warga atau nomor pengajuan"', false)
            ->assertDontSee('Tidak perlu persetujuan Admin');
    }
});

it('offers specific input examples while keeping labels and read only pages intact', function (): void {
    $this->seed(WorkflowFixture::class);
    $before = LedgerEntry::count();
    foreach ([
        ['warga', '/warga/penjemputan/ajukan', 'Contoh: Kp. Sukamaju RT 01/RW 02, dekat masjid'],
        ['warga', '/warga/pencairan/ajukan', 'Contoh: 25000'],
        ['warga', '/warga/estimasi', 'Contoh: 1,25'],
        ['petugas', '/petugas/pindai', 'Contoh: Siti atau CST-00000001'],
        ['petugas', '/petugas/riwayat', 'Contoh: nama warga atau nomor transaksi'],
        ['bendahara', '/profil/kata-sandi', 'Masukkan kata sandi saat ini'],
    ] as [$role, $path, $example]) {
        $this->actingAs(User::where('username', $role)->firstOrFail())->get($path)->assertOk()
            ->assertSee('placeholder="'.$example.'"', false)->assertSee('<label ', false);
    }
    expect(LedgerEntry::count())->toBe($before);
});

it('renders decorative headings and accessible icon actions from existing assets', function (): void {
    $heading = Blade::render('<x-ui.page-heading id="test-title" title="Layanan" mascot="3" />');
    expect($heading)->toContain('id="test-title"', 'data-page-heading', 'mascot-3.png', 'aria-hidden="true"');
    $card = Blade::render('<x-ui.action-card href="/warga/layanan" icon="truck" title="Minta penjemputan" description="Atur jadwal dari rumah." />');
    expect($card)->toContain('data-action-card', 'app-interactive', 'href="/warga/layanan"', 'Minta penjemputan', 'aria-hidden="true"')
        ->not->toContain('tabindex="-1"');
});

it('uses a real button for method selection and keeps navigation cards as links', function (): void {
    $card = Blade::render('<x-ui.action-card icon="banknote" title="Tunai" wire:click="chooseCash" />');
    expect($card)->toContain('<button type="button"', 'wire:click="chooseCash"', 'data-action-card', 'app-interactive', '</button>')->not->toContain('<a ');
});

it('gives every editable text primitive an example without faking native date placeholders', function (): void {
    $paths = [];
    foreach (['citizen', 'officer', 'treasurer', 'auth', 'profile'] as $surface) {
        array_push($paths, ...glob(resource_path('views/livewire/'.$surface.'/*.blade.php')));
    }
    $paths[] = resource_path('views/livewire/service-task-queue.blade.php');
    foreach ($paths as $path) {
        preg_match_all('/<x-ui\.(?:input|textarea)\b[\s\S]*?\/>/', file_get_contents($path), $matches);
        foreach ($matches[0] as $control) {
            if (preg_match('/\btype="(?:date|time|datetime-local|month|hidden)"|\breadonly\b/', $control) === 1) {
                continue;
            }
            expect($control, basename($path).' needs a meaningful input example')->toMatch('/\bplaceholder="[^"\s][^"]+"/');
        }
    }
});
