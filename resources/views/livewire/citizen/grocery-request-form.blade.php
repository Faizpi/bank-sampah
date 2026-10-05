<x-slot:title>Ajukan sembako</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="grocery-request-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penukaran Saldo</p>
            <h1 id="grocery-request-title" class="mt-2 text-h1 font-bold text-deep-green">Pilih Paket Sembako</h1>
            <p class="mt-3 text-body text-text-secondary">
                Nilai paket dicatat sebagai rekaman pengajuan. Dana ditahan sementara dan baru menjadi saldo keluar setelah paket diserahkan secara sah.
            </p>
        </div>
        <x-ui.mascot variant="3" bubble="Tukar saldo dengan sembako!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    @php
        $selectedPackage = $packages->firstWhere('id', (int) $packageId);
        $selectedPackageIsAffordable = $selectedPackage !== null && $availableBalance >= $selectedPackage->value;
    @endphp

    <div role="status" aria-live="polite" class="flex items-center gap-3 rounded-xl border border-forest-600 bg-success-bg px-4 py-3.5">
        <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
            <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
        </svg>
        <div>
            <p class="text-caption text-forest-700">Saldo tersedia</p>
            <p class="amount-tabular text-label font-bold text-forest-700">Rp{{ number_format($availableBalance, 0, ',', '.') }}</p>
        </div>
    </div>

    {{-- Package Selection --}}
     <x-ui.panel title="Paket aktif" description="Periksa isi dan nilai penukaran sebelum memilih. Ketersediaan dikonfirmasi admin.">
        @if ($serviceAreas->count() > 1)
            <label class="mb-5 block">
                <span class="text-label font-semibold text-deep-green">Area layanan</span>
                <select wire:model.live="serviceAreaId" class="mt-2 block w-full rounded-xl border-border bg-warm-canvas text-body text-deep-green">
                    <option value="">Pilih area layanan</option>
                    @foreach ($serviceAreas as $serviceArea)
                        <option value="{{ $serviceArea->id }}">{{ $serviceArea->name }}</option>
                    @endforeach
                </select>
                @error('serviceAreaId')
                    <p role="alert" class="mt-2 text-body-sm text-terracotta">{{ $message }}</p>
                @enderror
            </label>
        @endif
        <fieldset>
            <legend class="text-label font-semibold text-deep-green">Pilih paket sembako</legend>
            <div class="mt-3 grid gap-3 md:grid-cols-2">
                @forelse ($packages as $package)
                    @php
                        $isSelected = $selectedPackage?->id === $package->id;
                        $isAffordable = $availableBalance >= $package->value;
                        $shortfall = max(0, $package->value - $availableBalance);
                    @endphp
                    <label wire:key="grocery-package-{{ $package->id }}" @class([
                        'block',
                        'cursor-pointer' => $isAffordable,
                        'cursor-not-allowed' => ! $isAffordable,
                    ])>
                        <span @class([
                            'block min-h-full rounded-xl border-2 p-4 transition',
                            'border-forest-600 bg-success-bg' => $isSelected && $isAffordable,
                            'border-border bg-warm-canvas hover:border-forest-600' => ! $isSelected && $isAffordable,
                            'border-border bg-disabled-bg opacity-75' => ! $isAffordable,
                        ])>
                            <span class="flex items-start justify-between gap-4">
                                <span class="text-body font-bold text-deep-green">{{ $package->name }}</span>
                                <span class="flex shrink-0 items-center gap-2">
                                    <span class="rounded-full bg-harvest-gold px-2.5 py-1 text-caption font-bold text-deep-green">Rp{{ number_format($package->value, 0, ',', '.') }}</span>
                                    <input
                                        type="radio"
                                        wire:model.live="packageId"
                                        name="packageId"
                                        value="{{ $package->id }}"
                                        @checked($isSelected)
                                        @disabled(! $isAffordable)
                                        class="mt-0.5 size-5 accent-forest-600"
                                        aria-label="Pilih {{ $package->name }}"
                                    >
                                </span>
                            </span>
                            <span class="mt-3 block text-caption font-semibold uppercase tracking-wide text-text-secondary">Isi paket</span>
                            <span class="mt-1 block whitespace-pre-line text-body-sm leading-relaxed text-text-secondary">{{ $package->contents }}</span>
                            <span class="mt-4 block text-label font-semibold" @class([
                                'text-forest-700' => $isSelected && $isAffordable,
                                'text-text-secondary' => ! $isSelected && $isAffordable,
                                'text-terracotta' => ! $isAffordable,
                            ])>
                                @if (! $isAffordable)
                                    Saldo belum cukup · kurang Rp{{ number_format($shortfall, 0, ',', '.') }}
                                @elseif ($isSelected)
                                    Paket dipilih
                                @else
                                    Pilih paket ini
                                @endif
                            </span>
                        </span>
                    </label>
                @empty
                    <p class="rounded-xl border border-border bg-warm-canvas px-4 py-5 text-body-sm text-text-secondary md:col-span-2">Belum ada paket sembako aktif. Hubungi admin untuk mengaktifkan paket penukaran.</p>
                @endforelse
            </div>
        </fieldset>

        @error('packageId')
            <p role="alert" class="mt-3 text-body-sm text-terracotta">{{ $message }}</p>
        @enderror
        @error('request')
            <p role="alert" class="mt-3 text-body-sm text-terracotta">{{ $message }}</p>
        @enderror

        @if ($selectedPackage)
            <div @class([
                'mt-4 rounded-xl border px-4 py-3.5',
                'border-success-bg bg-success-bg' => $selectedPackageIsAffordable,
                'border-terracotta bg-danger-bg' => ! $selectedPackageIsAffordable,
            ])>
                @if ($selectedPackageIsAffordable)
                    <p class="text-label font-semibold text-deep-green">Saldo yang akan ditahan</p>
                    <p class="mt-1 text-body-sm text-text-secondary">Rp{{ number_format($selectedPackage->value, 0, ',', '.') }} untuk {{ $selectedPackage->name }}. Saldo baru dicatat keluar setelah paket diserahkan secara sah.</p>
                @else
                    <p class="text-label font-semibold text-terracotta">Saldo belum cukup</p>
                    <p class="mt-1 text-body-sm text-text-secondary">Pilih paket yang nilainya tidak melebihi saldo tersedia.</p>
                @endif
            </div>
        @endif
    </x-ui.panel>

    <div class="flex justify-end">
        <x-ui.button type="button" wire:click="submit" wire:loading.attr="disabled" wire:target="submit" :disabled="$selectedPackage === null || ! $selectedPackageIsAffordable">
            <span wire:loading.remove wire:target="submit">Ajukan Paket</span>
            <span wire:loading wire:target="submit">Memproses...</span>
        </x-ui.button>
    </div>
</section>
