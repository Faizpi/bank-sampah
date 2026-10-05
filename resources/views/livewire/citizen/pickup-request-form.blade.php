<x-slot:title>Ajukan penjemputan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

@php
    $selectedArea = $areas->firstWhere('id', (int) $serviceAreaId);
    $estimatedWeight = collect($items)->sum(static fn (array $item): float => (float) ($item['estimated_weight_kg'] ?? 0));
    $steps = [1 => 'Lokasi', 2 => 'Jenis & foto', 3 => 'Tinjau'];
    $dateOptions = collect($availableDates)->mapWithKeys(static fn (string $date): array => [
        $date => \Illuminate\Support\Carbon::parse($date)->translatedFormat('l, d F Y'),
    ])->all();
    $photoPickerInitialFiles = collect($photos)
        ->map(static fn ($photo): array => [
            'name' => $photo->getClientOriginalName(),
            'size' => $photo->getSize(),
            'mimeType' => (string) ($photo->getMimeType() ?? ''),
            'previewUrl' => $photo->temporaryUrl(),
        ])
        ->values()
        ->all();
@endphp

<section
    aria-labelledby="pickup-request-title"
    x-data
    x-on:focus-pickup-errors.window="$nextTick(() => $refs.pickupErrorSummary?.focus())"
    class="grid gap-6"
>
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penjemputan sampah</p>
            <h1 id="pickup-request-title" class="mt-2 text-h1 font-bold text-deep-green">Ajukan penjemputan</h1>
            <p class="mt-3 max-w-2xl text-body text-text-secondary">
                Isi tiga langkah singkat. Perkiraan hanya membantu perencanaan; saldo mengikuti penimbangan aktual petugas.
            </p>
        </div>
        <x-ui.mascot variant="4" bubble="Siap jemput sampahmu!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    @if ($errors->any())
        <section
            id="pickup-error-summary"
            x-ref="pickupErrorSummary"
            tabindex="-1"
            role="alert"
            aria-labelledby="pickup-error-title"
            class="rounded-lg border border-terracotta bg-danger-bg p-4"
        >
            <div class="flex gap-3">
                <svg viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-terracotta" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><path d="M12 8v4m0 4h.01"/></svg>
                <div>
                    <h2 id="pickup-error-title" class="text-title font-bold text-deep-green">Periksa pengajuan Anda</h2>
                    <p class="mt-1 text-body-sm text-text-primary">Perbaiki bagian berikut sebelum melanjutkan.</p>
                    <ul class="mt-2 list-disc space-y-1 pl-5 text-body-sm text-text-primary">
                        @foreach ($errors->all() as $message)
                            <li>{{ $message }}</li>
                        @endforeach
                    </ul>
                </div>
            </div>
        </section>
    @endif

    <nav aria-label="Tahap pengajuan penjemputan" class="rounded-lg border border-border bg-surface p-3 shadow-xs">
        <ol class="grid gap-2 sm:grid-cols-3">
            @foreach ($steps as $number => $label)
                <li>
                    <button
                        type="button"
                        wire:click="goToStep({{ $number }})"
                        @disabled($number >= $step)
                        @class([
                            'flex min-h-touch w-full items-center gap-3 rounded-md px-3 text-left transition',
                            'bg-forest-600 text-white' => $number === $step,
                            'bg-success-bg text-forest-700' => $number < $step,
                            'text-text-secondary disabled:cursor-default' => $number > $step,
                        ])
                        @if ($number === $step) aria-current="step" @endif
                    >
                        <span @class([
                            'flex size-8 shrink-0 items-center justify-center rounded-full border text-label font-bold',
                            'border-white/60' => $number === $step,
                            'border-forest-600 bg-surface text-forest-700' => $number < $step,
                            'border-border' => $number > $step,
                        ])>{{ $number }}</span>
                        <span>
                            <span class="block text-caption">Langkah {{ $number }}</span>
                            <span class="block text-label font-bold">{{ $label }}</span>
                        </span>
                    </button>
                </li>
            @endforeach
        </ol>
    </nav>

    <form wire:submit="submit" class="grid gap-6">
        @if ($step === 1)
            <x-ui.panel title="Lokasi dan tanggal layanan" description="Pilih area aktif dan tanggal yang masih tersedia.">
                <div class="grid gap-4 md:grid-cols-2">
                    <x-ui.select wire:model.live="serviceAreaId" label="Area pelayanan" name="serviceAreaId" placeholder="Pilih area pelayanan" :options="$areas->pluck('name', 'id')->all()" :error="$errors->first('serviceAreaId')" />
                    @if ($serviceAreaId !== '' && $dateOptions === [])
                        <div class="rounded-md border border-border bg-warm-canvas p-4 text-body-sm text-text-secondary">
                            Belum ada tanggal penjemputan yang tersedia untuk area ini. Pilih area lain atau coba lagi setelah petugas menambahkan kapasitas.
                        </div>
                    @else
                        <x-ui.select wire:model="selectedDate" label="Tanggal tersedia" name="selectedDate" placeholder="Pilih tanggal tersedia" :options="$dateOptions" :error="$errors->first('selectedDate')" />
                    @endif
                    <x-ui.textarea wire:model="address" label="Alamat penjemputan" name="address" rows="4" class="md:col-span-2" :error="$errors->first('address')" />
                    <x-ui.textarea wire:model="notes" label="Catatan akses (opsional)" name="notes" rows="3" class="md:col-span-2"
                        hint="Contoh: rumah cat biru, masuk gang sebelah masjid" :error="$errors->first('notes')" />
                </div>
                <div class="mt-5 rounded-md bg-info-bg px-4 py-3 text-body-sm text-deep-green">
                    Setelah dikirim, pengajuan akan diperiksa petugas lalu dijadwalkan sesuai kapasitas area.
                </div>
            </x-ui.panel>
        @elseif ($step === 2)
            <x-ui.panel title="Jenis dan perkiraan" description="Isi minimal satu jenis dan perkiraan beratnya.">
                <div class="grid gap-3">
                    @foreach ($items as $index => $item)
                        <div wire:key="pickup-item-{{ $index }}" class="grid gap-3 rounded-md border border-border bg-warm-canvas p-4 md:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_auto] md:items-start">
                            <x-ui.select wire:model="items.{{ $index }}.waste_type_id" label="Jenis sampah" name="items.{{ $index }}.waste_type_id" placeholder="Pilih jenis sampah"
                                :options="$types->pluck('name', 'id')->all()" :error="$errors->first('items.'.$index.'.waste_type_id')" />
                            <x-ui.input wire:model="items.{{ $index }}.estimated_weight_kg" label="Total berat (kg)" name="items.{{ $index }}.estimated_weight_kg" inputmode="decimal"
                                :error="$errors->first('items.'.$index.'.estimated_weight_kg')" />
                            <x-ui.input wire:model="items.{{ $index }}.estimated_quantity" label="Jumlah wadah" name="items.{{ $index }}.estimated_quantity" inputmode="numeric"
                                :error="$errors->first('items.'.$index.'.estimated_quantity')" />
                            @if (count($items) > 1)
                                <div class="md:pt-7">
                                    <button type="button" wire:click="removeItem({{ $index }})" class="inline-flex min-h-touch w-full items-center justify-center rounded-md border-2 border-terracotta px-4 text-label font-bold text-terracotta transition hover:bg-danger-bg">Hapus</button>
                                </div>
                            @endif
                            <p class="text-body-sm text-text-secondary md:col-span-4">Jumlah wadah opsional, misalnya 2 kantong atau 1 karung.</p>
                        </div>
                    @endforeach
                    <button type="button" wire:click="addItem" class="inline-flex min-h-touch items-center gap-2 justify-self-start rounded-md border-2 border-forest-600 px-4 text-label font-bold text-forest-700 transition hover:bg-success-bg">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Tambah jenis
                    </button>
                </div>
            </x-ui.panel>

            <x-ui.panel title="Foto wajib" description="Unggah 1–2 foto sampah.">
                <x-ui.media-picker
                    id="pickup-photos"
                    property="photos"
                    label="Foto sampah"
                    hint="Ambil atau pilih 1–2 foto sampah."
                    :max="2"
                    :multiple="true"
                    remove-method="removePhoto"
                    confirm-method="confirmPhotoUploads"
                    :initial-files="$photoPickerInitialFiles"
                />
                @error('photos')<p class="text-body-sm font-semibold text-terracotta">{{ $message }}</p>@enderror
            </x-ui.panel>
        @else
            <x-ui.panel title="Ringkasan pengajuan" description="Periksa kembali detail sebelum pengajuan dikirim.">
                <dl class="grid gap-4 sm:grid-cols-2">
                    <div><dt class="text-caption text-text-secondary">Area pelayanan</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ $selectedArea?->name ?? 'Belum dipilih' }}</dd></div>
                    <div><dt class="text-caption text-text-secondary">Tanggal pilihan</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ $selectedDate !== '' ? \Illuminate\Support\Carbon::parse($selectedDate)->translatedFormat('d F Y') : 'Belum dipilih' }}</dd></div>
                    <div class="sm:col-span-2"><dt class="text-caption text-text-secondary">Alamat</dt><dd class="mt-1 text-body text-text-primary">{{ $address !== '' ? $address : 'Belum diisi' }}</dd></div>
                    <div><dt class="text-caption text-text-secondary">Jenis sampah</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ count($items) }} jenis</dd></div>
                    <div><dt class="text-caption text-text-secondary">Foto</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ count($photos) }} dari 2 foto</dd></div>
                    <div><dt class="text-caption text-text-secondary">Perkiraan berat</dt><dd class="mt-1 text-label font-bold text-deep-green">{{ \App\Support\WeightFormatter::format($estimatedWeight) }} kg</dd></div>
                </dl>
                <div class="mt-5 border-t border-border pt-4">
                    <h3 class="text-label font-bold text-deep-green">Setelah dikirim</h3>
                    <ol class="mt-4 grid list-none gap-x-5 gap-y-3 text-body-sm text-text-secondary sm:grid-cols-3">
                        <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                            <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-success-bg text-caption font-bold text-forest-700">1</span>
                            <p class="pt-0.5 leading-5">Pengajuan diperiksa petugas.</p>
                        </li>
                        <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                            <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-success-bg text-caption font-bold text-forest-700">2</span>
                            <p class="pt-0.5 leading-5">Jadwal dikonfirmasi sesuai kapasitas.</p>
                        </li>
                        <li class="grid grid-cols-[1.75rem_minmax(0,1fr)] items-start gap-3">
                            <span aria-hidden="true" class="flex size-7 items-center justify-center rounded-sm bg-success-bg text-caption font-bold text-forest-700">3</span>
                            <p class="pt-0.5 leading-5">Saldo dicatat dari timbangan aktual.</p>
                        </li>
                    </ol>
                </div>
            </x-ui.panel>
        @endif

        <div class="flex flex-col items-end gap-3 sm:flex-row sm:justify-end">
            @if ($step > 1)
                <x-ui.button type="button" variant="secondary" wire:click="previousStep">Kembali</x-ui.button>
            @endif

            @if ($step < 3)
                <x-ui.button type="button" wire:click="nextStep" wire:loading.attr="disabled" wire:target="nextStep" data-photo-picker-action>Lanjut ke {{ $steps[$step + 1] }}</x-ui.button>
            @else
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="submit">
                    <span wire:loading.remove wire:target="submit">Kirim pengajuan</span>
                    <span wire:loading wire:target="submit">Memproses...</span>
                </x-ui.button>
            @endif
        </div>
    </form>
</section>
