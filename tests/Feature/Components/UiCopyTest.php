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
        'resources/views/filament/backoffice/directory.blade.php' => ['Pilih sudut pandang', 'dua menu yang membingungkan', 'Nasabah memuat kartu dan data warga.'],
        'resources/views/filament/backoffice/technical-settings.blade.php' => ['Atur ambang operasional yang aman.', 'Rahasia aplikasi, kata sandi, token', 'Batas Rp5.000 adalah aturan aplikasi', 'kanal Xendit tertentu dapat punya batas sendiri'],
        'resources/views/filament/backoffice/waste-catalog.blade.php' => ['dari satu area yang mudah ditemukan'],
        'resources/views/filament/backoffice/regions.blade.php' => ['di satu tempat agar penugasan dan wilayah layanan tetap konsisten'],
        'resources/views/livewire/citizen/deposit-history.blade.php' => ['Bukti final, kanal layanan, dan status transaksi.', 'Nilai sebelum, sesudah, alasan, dan dampak saldo.'],
        'resources/views/livewire/citizen/grocery-history.blade.php' => ['Paket, nilai, status, detail, dan bukti penyerahan.'],
        'resources/views/livewire/citizen/pickup-show.blade.php' => ['Rincian dan foto ini tersimpan bersama pengajuan Anda.', 'berhasil difinalkan'],
        'resources/views/livewire/citizen/withdrawal-receipt.blade.php' => ['Butuh arsip?', 'Saldo keluar tercatat setelah pembayaran sah.'],
        'resources/views/livewire/citizen/grocery-receipt.blade.php' => ['Bukti penukaran pribadi Anda.', 'Cetak bukti atau simpan sebagai PDF.'],
        'resources/views/livewire/officer/customer-identification.blade.php' => ['Kodenya tidak ditampilkan di layar.'],
        'resources/views/livewire/treasurer/reports.blade.php' => ['Gunakan pilihan cepat atau tentukan tanggal sendiri.', 'Laporan Excel mengikuti periode dan filter yang dipilih.'],
        'app/Filament/Pages/Reconciliation.php' => ['tidak perlu Admin kedua'],
        'app/Domain/Telegram/Services/PayoutTelegramBot.php' => ['Hanya tujuan yang sudah dikonfigurasi yang tersedia.'],
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
        ->toContain('Simulasi. Uang asli tidak dikirim.', 'Saldo masih disisihkan', 'Saldo yang disisihkan sudah tersedia kembali.');
    expect(file_get_contents(resource_path('views/livewire/citizen/payout-form.blade.php')))
        ->toContain('Nama dan nomor tujuan dikirim ke Xendit untuk simulasi.', 'Konfirmasi pencairan simulasi');
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

it('keeps financial limits and destructive action warnings after removing technical filler', function (): void {
    expect(file_get_contents(resource_path('views/filament/backoffice/technical-settings.blade.php')))
        ->toContain('Minimum pencairan (Rp)', 'min="5000"', 'max="1000000"', 'Simpan pengaturan');
    expect(file_get_contents(resource_path('views/filament/backoffice/technical-backups.blade.php')))
        ->toContain('Buat berkas cadangan secara terpisah', 'Halaman ini tidak memulihkan data.');
    expect(file_get_contents(resource_path('views/filament/backoffice/technical-audit-retention.blade.php')))
        ->toContain('dihapus dan tidak dapat dipulihkan', 'Pratinjau berlaku 10 menit.');
    expect(file_get_contents(resource_path('views/filament/backoffice/technical-media-retention.blade.php')))
        ->toContain('dihapus permanen', '$mediaRetentionMinimumAgeDays', '$mediaRetentionBatchLimit');
    expect(file_get_contents(resource_path('views/livewire/service-task-queue.blade.php')))
        ->toContain('Pastikan nama penerima dan nominal sesuai.', 'Konfirmasi hanya jika uang tunai sudah diterima warga.');
});
