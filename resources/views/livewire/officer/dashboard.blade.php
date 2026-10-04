<x-slot:title>Tugas hari ini</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<x-slot:todayTasks>
    @if ($priorityTask)
        <section class="mb-4 rounded-lg border border-sky-blue/40 bg-info-bg p-5 shadow-xs" aria-labelledby="priority-task-title">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div>
                    <p class="text-caption font-semibold text-sky-blue">Fokus sekarang</p>
                    <h2 id="priority-task-title" class="mt-1 text-h2 font-bold text-deep-green">{{ $priorityTask['label'] }}</h2>
                    <p class="mt-1 text-body-sm text-text-secondary">{{ $priorityTask['description'] }}</p>
                    @if ($priorityTask['status'])<p class="mt-2 text-caption font-semibold text-sky-blue">Status: {{ \App\Support\StatusLabel::for($priorityTask['status']) }}</p>@endif
                </div>
                @if ($priorityTask['href'])
                    <a href="{{ $priorityTask['href'] }}" class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md border border-sky-blue bg-surface px-5 text-label font-bold text-sky-blue transition hover:bg-surface/70">
                        Buka tugas
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 12h14M12 5l7 7-7 7"/></svg>
                    </a>
                @endif
            </div>
        </section>
    @endif

    <x-ui.panel title="Antrean hari ini" description="Hanya tugas yang ditugaskan kepada Anda yang ditampilkan.">
        @if ($todayPickups->isEmpty())
            <x-ui.empty-state
                title="Belum ada tugas hari ini"
                description="Saat ada tugas yang ditugaskan kepada Anda, tugas tersebut akan muncul di sini." />
        @else
            <div class="grid gap-3">
                @foreach ($todayPickups as $pickup)
                    @if ($canOperatePickups)
                    <a href="{{ route('officer.pickup.task', $pickup) }}" class="flex min-h-[72px] items-center justify-between gap-3 rounded-xl border border-border bg-warm-canvas p-4 transition hover:border-forest-600 hover:shadow-xs">
                    @else
                    <div class="flex min-h-[72px] items-center justify-between gap-3 rounded-xl border border-border bg-warm-canvas p-4">
                    @endif
                        <span class="min-w-0">
                            <span class="block text-label font-bold text-deep-green">{{ $pickup->request_number }}</span>
                            <span class="mt-1 block truncate text-body-sm text-text-secondary">{{ $pickup->customer?->name ?? 'Nasabah' }} · {{ $pickup->address }}</span>
                        </span>
                        <span class="shrink-0 rounded-full border border-info-bg bg-info-bg px-3 py-1 text-caption font-semibold text-sky-blue">{{ ucwords(str_replace('_', ' ', $pickup->status->value)) }}</span>
                    @if ($canOperatePickups)
                    </a>
                    @else
                    </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.panel>
</x-slot:todayTasks>

<div class="grid gap-6">
    <section aria-labelledby="officer-dashboard-title" class="rounded-2xl border border-border bg-surface p-5 shadow-xs sm:p-6">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <div class="flex items-center gap-2">
                    <img src="{{ asset('images/landing/mascot-3.png') }}" alt="" class="size-7 object-contain" aria-hidden="true">
                    <span class="text-caption font-semibold text-forest-600 uppercase tracking-wide">Petugas Bank Sampah</span>
                </div>
                <h2 id="officer-dashboard-title" class="mt-2 text-h2 font-bold text-deep-green">Siap menjalankan tugas?</h2>
                <p class="mt-1.5 text-body-sm text-text-secondary">Pantau tugas yang ditugaskan kepada Anda, lalu lanjutkan setoran atau layanan keliling.</p>
            </div>
            <x-ui.mascot variant="11" bubble="Siap membantu warga!" bubblePosition="top" class="h-24 w-auto shrink-0 sm:h-28" animate />
        </div>
    </section>

    <section aria-labelledby="officer-actions-title">
        <h2 id="officer-actions-title" class="mb-3 text-label font-bold text-text-secondary">Aksi cepat</h2>
        <div class="grid grid-cols-2 gap-3">
            @if ($canIdentifyCustomers)
            <a href="{{ $identificationHref }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-forest-600 hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-success-bg text-forest-600 transition-colors group-hover:bg-forest-600 group-hover:text-white">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="11" cy="11" r="8"/><path d="m21 21-4.3-4.3"/>
                    </svg>
                </div>
                <span class="text-caption font-semibold text-deep-green">Mulai setoran</span>
            </a>
            @endif

            @if ($canAccessMobileServices)
            <a href="{{ route('officer.mobile-services') }}"
                class="group flex flex-col items-center gap-2.5 rounded-xl border border-border bg-surface p-4 text-center shadow-xs transition duration-200 hover:-translate-y-0.5 hover:border-sky-blue hover:shadow-sm">
                <div class="flex size-11 items-center justify-center rounded-xl bg-info-bg text-sky-blue transition-colors group-hover:bg-sky-blue group-hover:text-white">
                    <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>
                    </svg>
                </div>
                <span class="text-caption font-semibold text-deep-green">Layanan keliling</span>
            </a>
            @endif

        </div>
    </section>

@if ($canViewPickups || $canViewDeposits || $canShowGroceryTasks || $canViewMobileServices)
<section aria-labelledby="officer-queues-title" class="grid gap-4 lg:grid-cols-2">
    <h2 id="officer-queues-title" class="sr-only">Antrean kerja petugas</h2>
    @if ($canViewPickups)
    <x-ui.panel title="Pickup terlambat" description="Prioritaskan tugas yang melewati tanggal jadwal.">
        @if ($latePickups->isEmpty())
            <x-ui.empty-state title="Tidak ada pickup terlambat" description="Semua pickup dalam antrean Anda masih sesuai jadwal." />
        @else
            <div class="grid gap-3">
                @foreach ($latePickups as $pickup)
                    @if ($canOperatePickups)
                    <a href="{{ route('officer.pickup.task', $pickup) }}" class="block rounded-xl border border-danger-bg bg-danger-bg p-4 transition hover:border-terracotta">
                    @else
                    <div class="block rounded-xl border border-danger-bg bg-danger-bg p-4">
                    @endif
                        <p class="text-label font-bold text-deep-green">{{ $pickup->request_number }}</p>
                        <p class="mt-1 text-body-sm text-text-secondary">{{ $pickup->customer?->name ?? 'Nasabah' }} · {{ $pickup->address }}</p>
                    @if ($canOperatePickups)
                    </a>
                    @else
                    </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.panel>
    @endif

    @if ($canViewDeposits)
    <x-ui.panel title="Draf setoran" description="Lanjutkan draf yang dibuat oleh akun Anda.">
        @if ($draftDeposits->isEmpty())
            <x-ui.empty-state title="Tidak ada draf setoran" description="Draf setoran Anda yang belum selesai akan muncul di sini." />
        @else
            <div class="grid gap-3">
                @foreach ($draftDeposits as $deposit)
                    @if ($canResumeDeposits)
                    <a href="{{ route('officer.deposit-form', ['customerId' => $deposit->customer_id, 'draftId' => $deposit->id]) }}" class="block rounded-xl border border-warning-bg bg-warning-bg p-4 transition hover:border-harvest-gold">
                    @else
                    <div class="block rounded-xl border border-warning-bg bg-warning-bg p-4">
                    @endif
                        <p class="text-label font-bold text-deep-green">{{ $deposit->deposit_number }}</p>
                        <p class="mt-1 text-body-sm text-text-secondary">{{ $deposit->customer?->name ?? 'Nasabah' }} · Draf belum difinalisasi</p>
                    @if ($canResumeDeposits)
                    </a>
                    @else
                    </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.panel>
    @endif

    @if ($canShowGroceryTasks)
    <x-ui.panel title="Tugas sembako" description="Persiapan dan penyerahan sesuai penugasan.">
        @if ($groceryTasks->isEmpty())
            <x-ui.empty-state title="Tidak ada tugas sembako" description="Tugas sembako yang siap diproses akan muncul di sini." />
        @else
            <div class="grid gap-3">
                @foreach ($groceryTasks as $redemption)
                    @if ($canAccessGroceryTasks)
                    <a href="{{ $groceryTasksHref }}" class="block rounded-xl border border-border bg-warm-canvas p-4 transition hover:border-harvest-gold">
                    @else
                    <div class="block rounded-xl border border-border bg-warm-canvas p-4">
                    @endif
                        <p class="text-label font-bold text-deep-green">{{ $redemption->request_number }}</p>
                        <p class="mt-1 text-body-sm text-text-secondary">{{ $redemption->customer?->name ?? 'Nasabah' }} · {{ ucwords(str_replace('_', ' ', $redemption->status->value)) }}</p>
                    @if ($canAccessGroceryTasks)
                    </a>
                    @else
                    </div>
                    @endif
                @endforeach
            </div>
        @endif
    </x-ui.panel>
    @endif

    @if ($canViewMobileServices)
    <x-ui.panel title="Layanan keliling" description="Buka atau tutup titik yang menugaskan Anda.">
        @if ($mobileServices->isEmpty())
            <x-ui.empty-state title="Tidak ada jadwal keliling" description="Jadwal yang menugaskan Anda akan muncul di sini." />
        @else
            <div class="grid gap-3">
                @foreach ($mobileServices as $service)
                    <a href="{{ route('officer.mobile-services') }}" class="block rounded-xl border border-info-bg bg-info-bg p-4 transition hover:border-sky-blue">
                        <p class="text-label font-bold text-deep-green">{{ $service->service_number }} · {{ $service->point }}</p>
                        <p class="mt-1 text-body-sm text-text-secondary">{{ $service->starts_at->format('d M Y, H:i') }} · {{ ucwords(str_replace('_', ' ', $service->status->value)) }}</p>
                    </a>
                @endforeach
            </div>
        @endif
    </x-ui.panel>
    @endif
</section>
@endif

</div>{{-- /grid gap-6 (Livewire single root) --}}
