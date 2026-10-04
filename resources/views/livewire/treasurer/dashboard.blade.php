<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<x-slot:todayTasks>
    <x-ui.panel title="Pencairan siap dibayar" description="Hanya pencairan dalam scope Anda yang tampil.">
        <x-ui.empty-state
            title="Belum ada pencairan siap dibayar"
            description="Pencairan yang disetujui dan siap dibayar akan muncul di sini." />
    </x-ui.panel>
</x-slot:todayTasks>

<div class="grid gap-6">

{{-- Header + Mascot --}}
<section aria-labelledby="treasurer-dashboard-title" class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                <span class="text-caption font-semibold text-harvest-gold uppercase tracking-wide">Bendahara Bank Sampah</span>
            </div>
            <h1 id="treasurer-dashboard-title" class="mt-2 text-h2 font-bold text-deep-green">Selamat bertugas!</h1>
            <p class="mt-1.5 text-body-sm text-text-secondary">
                {{ now()->translatedFormat('l, d F Y') }} — Pembayaran dan rekonsiliasi sesuai akses Anda.
            </p>
        </div>
        <x-ui.mascot variant="12" bubble="Rekap keuangan hari ini!" bubblePosition="top" class="h-24 w-auto shrink-0 sm:h-28" animate />
    </div>
</section>

{{-- Quick Links --}}
<section aria-labelledby="treasurer-actions-title">
    <h2 id="treasurer-actions-title" class="mb-3 text-label font-bold text-text-secondary">Navigasi Cepat</h2>
    <div class="grid grid-cols-2 gap-3 sm:grid-cols-3">
        <a href="{{ route('treasurer.withdrawal.payments') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-harvest-gold hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-warning-bg text-harvest-gold transition-colors group-hover:bg-harvest-gold group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Proses Pembayaran</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Pencairan siap bayar</p>
            </div>
        </a>

        <a href="{{ route('treasurer.reports') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <rect x="5" y="4" width="14" height="17" rx="2"/><path d="M9 4a3 3 0 0 1 6 0M9 12h.01M13 12h2M9 16h.01M13 16h2"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Laporan &amp; Ekspor</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Unduh data setoran</p>
            </div>
        </a>

        <a href="{{ route('profile.password') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-border hover:shadow-sm sm:col-span-1 col-span-2">
            <div class="flex size-11 items-center justify-center rounded-xl bg-disabled-bg text-text-secondary transition-colors group-hover:bg-warm-canvas">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Profil Akun</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Kata sandi &amp; keamanan</p>
            </div>
        </a>
    </div>
</section>

{{-- Panduan --}}
<section>
    <x-ui.panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-ui.mascot variant="5" class="mx-auto h-20 w-auto sm:mx-0 sm:shrink-0" />
            <div>
                <p class="text-label font-semibold text-harvest-gold">Alur Pembayaran</p>
                <h2 class="mt-1 text-title font-bold text-deep-green">Verifikasi → Konfirmasi → Bukti</h2>
                <p class="mt-1.5 text-body-sm text-text-secondary">
                    Pastikan identitas penerima dan nominal sesuai sebelum pembayaran final. Bukti tersimpan otomatis untuk rekonsiliasi.
                </p>
                <a href="{{ route('treasurer.withdrawal.payments') }}" class="mt-3 inline-flex min-h-touch items-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green transition hover:opacity-90">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Lihat Pencairan
                </a>
            </div>
        </div>
    </x-ui.panel>
</section>

</div>{{-- /grid gap-6 (Livewire single root) --}}