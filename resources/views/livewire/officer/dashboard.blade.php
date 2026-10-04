<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<x-slot:todayTasks>
    <x-ui.panel title="Tugas hari ini" description="Hanya tugas yang ditugaskan kepada Anda yang ditampilkan.">
        <x-ui.empty-state
            title="Belum ada tugas hari ini"
            description="Saat ada tugas yang ditugaskan kepada Anda, tugas tersebut akan muncul di sini." />
    </x-ui.panel>
</x-slot:todayTasks>

<div class="grid gap-6">

{{-- Header + Mascot --}}
<section aria-labelledby="officer-dashboard-title" class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <div class="flex items-center gap-2">
                <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                <span class="text-caption font-semibold text-forest-600 uppercase tracking-wide">Petugas Bank Sampah</span>
            </div>
            <h1 id="officer-dashboard-title" class="mt-2 text-h2 font-bold text-deep-green">Selamat bertugas!</h1>
            <p class="mt-1.5 text-body-sm text-text-secondary">
                {{ now()->translatedFormat('l, d F Y') }} — Hanya data dalam scope Anda yang tampil.
            </p>
        </div>
        <x-ui.mascot variant="11" bubble="Siap membantu warga!" bubblePosition="top" class="h-24 w-auto shrink-0 sm:h-28" animate />
    </div>
</section>

{{-- Quick Actions --}}
<section aria-labelledby="officer-actions-title">
    <h2 id="officer-actions-title" class="mb-3 text-label font-bold text-text-secondary">Aksi Utama</h2>
    <div class="grid grid-cols-2 gap-3">
        <a href="{{ $identificationHref }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Identifikasi Warga</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Cari atau pindai nasabah</p>
            </div>
        </a>

        <a href="{{ route('officer.mobile-services') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-sky-blue hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-info-bg text-sky-blue transition-colors group-hover:bg-sky-blue group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Jadwal Keliling</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Titik layanan hari ini</p>
            </div>
        </a>

        <a href="{{ $groceryTasksHref }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-harvest-gold hover:shadow-sm">
            <div class="flex size-11 items-center justify-center rounded-xl bg-warning-bg text-harvest-gold transition-colors group-hover:bg-harvest-gold group-hover:text-white">
                <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18M16 10a4 4 0 0 1-8 0"/>
                </svg>
            </div>
            <div>
                <p class="text-caption font-semibold text-deep-green">Tugas Sembako</p>
                <p class="mt-0.5 text-body-sm text-text-secondary">Persiapan &amp; serah terima</p>
            </div>
        </a>

        <a href="{{ route('profile.password') }}"
            class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-border hover:shadow-sm">
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

{{-- Panduan Kerja --}}
<section aria-labelledby="officer-guide-title">
    <x-ui.panel>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center">
            <x-ui.mascot variant="8" class="mx-auto h-20 w-auto sm:mx-0 sm:shrink-0" />
            <div>
                <p class="text-label font-semibold text-forest-600">Alur Kerja Setoran</p>
                <h2 id="officer-guide-title" class="mt-1 text-title font-bold text-deep-green">Identifikasi → Timbang → Konfirmasi</h2>
                <p class="mt-1.5 text-body-sm text-text-secondary">
                    Mulai dengan identifikasi warga, lalu catat item timbang, dan akhiri dengan konfirmasi setoran untuk mencetak bukti.
                </p>
                <a href="{{ $identificationHref }}" class="mt-3 inline-flex min-h-touch items-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    Mulai sekarang
                </a>
            </div>
        </div>
    </x-ui.panel>
</section>

</div>{{-- /grid gap-6 (Livewire single root) --}}
