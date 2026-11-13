<?php

declare(strict_types=1);

it('keeps internal implementation notes out of routine user screens', function (): void {
    $files = [
        'resources/views/livewire/citizen/payout-show.blade.php' => ['Jangan mengajukan ulang', 'hasil provider'],
        'resources/views/livewire/citizen/payout-form.blade.php' => ['tanpa pembayaran oleh Bendahara'],
        'resources/views/livewire/officer/deposit-form.blade.php' => ['dihitung ulang server', 'Konteks setoran'],
        'resources/views/livewire/officer/pickup-task.blade.php' => ['tahapan berikutnya tetap redup'],
        'resources/views/livewire/public-site/public-programs.blade.php' => ['ambang publikasi', 'metrik yang diizinkan', 'ambang privasi publik'],
        'resources/views/filament/backoffice/reconciliation.blade.php' => ['tidak ada persetujuan kedua'],
        'resources/views/filament/backoffice/directory.blade.php' => ['Pilih sudut pandang', 'dua menu yang membingungkan'],
    ];
    foreach ($files as $file => $phrases) {
        $source = file_get_contents(base_path($file));
        foreach ($phrases as $phrase) {
            expect($source)->not->toContain($phrase);
        }
    }
});

it('preserves the important financial and privacy information while shortening UI copy', function (): void {
    expect(file_get_contents(resource_path('views/livewire/citizen/payout-show.blade.php')))
        ->toContain('tanpa pengiriman uang riil', 'bukan struk resmi', 'Saldo masih disisihkan', 'Saldo yang disisihkan sudah tersedia kembali.');
    expect(file_get_contents(resource_path('views/livewire/citizen/payout-form.blade.php')))
        ->toContain('Data tujuan dikirim ke Xendit Test Mode.', 'Konfirmasi pencairan simulasi');
    expect(file_get_contents(resource_path('views/livewire/officer/deposit-form.blade.php')))
        ->toContain('Harga mengikuti waktu draf setoran dibuat.', 'maksimal tiga angka desimal', 'Periksa nama warga dan hasil timbang');
    expect(file_get_contents(resource_path('views/livewire/citizen/withdrawal-show.blade.php')))
        ->toContain('Saldo disisihkan sampai uang tunai diserahkan.');
    expect(file_get_contents(resource_path('views/livewire/citizen/grocery-show.blade.php')))
        ->toContain('Saldo disisihkan sampai paket diserahkan.');
});

it('keeps authentication loading notices full width with an explicit flex display', function (): void {
    foreach (['login-form' => 'login', 'register-citizen-form' => 'register'] as $view => $action) {
        $source = file_get_contents(resource_path('views/livewire/auth/'.$view.'.blade.php'));
        expect($source)->toMatch('/<div\b(?=[^>]*class="[^"]*\bw-full\b)(?=[^>]*wire:loading\.flex)(?=[^>]*data-auth-loading="'.$action.'")[^>]*>/')
            ->toContain('role="status"', 'aria-live="polite"');
    }
});
