<x-slot:title>Jadwal layanan keliling</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section aria-labelledby="mobile-task-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Operasi Petugas</p>
            <h1 id="mobile-task-title" class="mt-2 text-h1 font-bold text-deep-green">Jadwal Layanan Keliling</h1>
            <p class="mt-3 text-body text-text-secondary">
                Hanya jadwal yang menugaskan Anda yang tampil. Status layanan menentukan apakah setoran keliling dapat dicatat.
            </p>
        </div>
        <x-ui.mascot variant="8" class="h-28 w-auto shrink-0" />
    </div>

    @if ($services->isEmpty())
        <div class="rounded-xl border border-border bg-surface p-6 text-center shadow-xs">
            <x-ui.mascot variant="9" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Belum ada jadwal ditugaskan</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Jadwal layanan keliling yang ditugaskan kepada Anda akan muncul di sini.</p>
        </div>
    @else
        <div class="grid gap-4">
            @foreach ($services as $service)
                @php
                    $canOpenNow = $service->starts_at->lessThanOrEqualTo(now()) && $service->ends_at->greaterThanOrEqualTo(now());
                    $isFull = $service->isFull();
                @endphp
                <article class="rounded-xl border border-border bg-surface p-5 shadow-xs transition hover:border-forest-600/40 hover:shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div>
                            <p class="text-caption font-semibold text-forest-600">
                                {{ $service->starts_at->format('d M Y, H:i') }}–{{ $service->ends_at->format('H:i') }}
                            </p>
                            <h2 class="mt-1 text-title font-bold text-deep-green">{{ $service->point }}</h2>
                        </div>
                        <span class="inline-flex items-center rounded-full border border-info-bg bg-info-bg px-3 py-1 text-caption font-semibold text-sky-blue">
                            {{ $isFull ? 'Penuh' : \App\Support\StatusLabel::for($service->status) }}
                        </span>
                    </div>

                    <p class="mt-2 text-body-sm text-text-secondary">
                        Slot warga/transaksi <strong>{{ $service->remainingCapacity() }}</strong>/{{ $service->capacity }} tersisa.
                    </p>

                    <div class="mt-4 flex flex-col items-end gap-2 sm:flex-row sm:justify-end">
                        @if ($service->status === \App\Domain\MobileServices\Enums\MobileServiceStatus::Published)
                            @if ($canOpenNow)
                                <button wire:click="open({{ $service->id }})"
                                    wire:loading.attr="disabled"
                                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:bg-disabled-bg disabled:text-text-secondary">
                                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="m10 8 6 4-6 4V8z"/></svg>
                                    Buka Layanan
                                </button>
                            @else
                                <button type="button" disabled
                                    class="inline-flex min-h-touch cursor-not-allowed items-center justify-center rounded-xl bg-disabled-bg px-5 text-label font-bold text-text-secondary">
                                    Dapat dibuka pukul {{ $service->starts_at->format('H:i') }}
                                </button>
                            @endif
                            @elseif ($service->status === \App\Domain\MobileServices\Enums\MobileServiceStatus::Open)
                            <a href="{{ route('officer.customer-identification', ['mobileServiceId' => $service->id]) }}"
                                class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-focus focus-visible:ring-offset-2">
                                Catat Setoran
                            </a>
                            <button wire:click="recap({{ $service->id }})" wire:loading.attr="disabled"
                                class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl border-2 border-forest-600 px-5 text-label font-bold text-forest-700 transition hover:bg-success-bg disabled:cursor-wait">
                                Rekap Layanan
                            </button>
                            <button wire:click="requestClose({{ $service->id }})"
                                wire:loading.attr="disabled"
                                class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl border-2 border-terracotta px-5 text-label font-bold text-terracotta transition hover:bg-danger-bg disabled:cursor-wait">
                                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><rect x="9" y="9" width="6" height="6"/></svg>
                                Tutup Layanan
                            </button>
                        @endif
                    </div>
                    @error('open.'.$service->id)
                        <p class="mt-2 text-body-sm font-semibold text-danger" role="alert">{{ $message }}</p>
                    @enderror
                    @if (session('mobile-recap-'.$service->id))
                        @php($recap = session('mobile-recap-'.$service->id))
                        <dl class="mt-4 grid gap-2 rounded-xl bg-warm-canvas p-4 text-body-sm sm:grid-cols-3">
                            <div><dt class="text-text-secondary">Transaksi</dt><dd class="font-bold text-deep-green">{{ $recap['transaction_count'] }}</dd></div>
                            <div><dt class="text-text-secondary">Berat</dt><dd class="font-bold text-deep-green">{{ \App\Support\WeightFormatter::format($recap['total_weight_kg']) }} kg</dd></div>
                            <div><dt class="text-text-secondary">Nilai</dt><dd class="font-bold text-deep-green">Rp {{ number_format($recap['total_value'], 0, ',', '.') }}</dd></div>
                        </dl>
                    @endif
                </article>
            @endforeach
        </div>
    @endif

    @if ($pendingCloseServiceId !== null)
        @php($pendingService = $services->firstWhere('id', $pendingCloseServiceId))
        <div class="fixed inset-0 z-overlay flex items-end justify-center bg-overlay p-4 sm:items-center" role="presentation">
            <div class="w-full max-w-form rounded-lg border border-border bg-surface p-5 shadow-dialog sm:p-6" role="dialog" aria-modal="true" aria-labelledby="close-mobile-service-title">
                <h2 id="close-mobile-service-title" class="text-h2 font-bold text-deep-green">Tutup layanan keliling?</h2>
                <p class="mt-2 text-body text-text-secondary">
                    {{ $pendingService?->point ?? 'Layanan ini' }} akan berhenti menerima transaksi keliling. Pastikan setoran yang sudah diterima sudah tercatat dan warga sudah mendapat informasi.
                </p>
                <div class="mt-5 flex flex-col-reverse gap-3 sm:flex-row sm:justify-end">
                    <x-ui.button type="button" variant="secondary" wire:click="cancelClose">Kembali</x-ui.button>
                    <x-ui.button type="button" variant="danger" wire:click="close({{ $pendingCloseServiceId }})" wire:loading.attr="disabled" wire:target="close">Tutup layanan</x-ui.button>
                </div>
            </div>
        </div>
    @endif
</section>
