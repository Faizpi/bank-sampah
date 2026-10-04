<x-slot:title>Ajukan penjemputan</x-slot:title>
<x-slot:context>Layanan warga</x-slot:context>

<section aria-labelledby="pickup-request-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Penjemputan Sampah</p>
            <h1 id="pickup-request-title" class="mt-2 text-h1 font-bold text-deep-green">Ajukan Penjemputan</h1>
            <p class="mt-3 text-body text-text-secondary">
                Perkiraan berat hanya membantu perencanaan kapasitas. Saldo mengikuti penimbangan aktual petugas.
            </p>
        </div>
        <x-ui.mascot variant="4" bubble="Siap jemput sampahmu!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Berhasil" :description="session('success')" />
    @endif

    {{-- Alamat & Tanggal --}}
    <x-ui.panel title="Alamat dan tanggal layanan" description="Pilih area aktif dan tanggal yang masih tersedia.">
        <div class="grid gap-4 md:grid-cols-2">
            <x-ui.select wire:model="serviceAreaId" label="Area pelayanan" name="serviceAreaId"
                :options="$areas->pluck('name', 'id')->all()" />
            <x-ui.input wire:model="selectedDate" label="Tanggal pilihan" name="selectedDate" type="date" />
            <x-ui.textarea wire:model="address" label="Alamat penjemputan" name="address" rows="4" class="md:col-span-2" />
            <x-ui.textarea wire:model="notes" label="Catatan akses (opsional)" name="notes" rows="3" class="md:col-span-2"
                hint="Contoh: 'Rumah cat biru, masuk gang sebelah masjid'" />
        </div>
    </x-ui.panel>

    {{-- Jenis & Perkiraan --}}
    <x-ui.panel title="Jenis dan perkiraan berat" description="Isi minimal satu jenis. Jangan memasukkan berat aktual di sini.">
        <div class="grid gap-4">
            @foreach ($items as $index => $item)
                <div wire:key="pickup-item-{{ $index }}" class="grid gap-3 rounded-xl border border-border bg-warm-canvas p-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end">
                    <x-ui.select wire:model="items.{{ $index }}.waste_type_id"
                        label="Jenis sampah" name="items.{{ $index }}.waste_type_id"
                        :options="$types->pluck('name', 'id')->all()" />
                    <x-ui.input wire:model="items.{{ $index }}.estimated_weight_kg"
                        label="Perkiraan berat (kg)" name="items.{{ $index }}.estimated_weight_kg" inputmode="decimal" />
                    <x-ui.input wire:model="items.{{ $index }}.estimated_quantity"
                        label="Perkiraan jumlah" name="items.{{ $index }}.estimated_quantity" inputmode="numeric" />
                    @if (count($items) > 1)
                        <button type="button" wire:click="removeItem({{ $index }})"
                            class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-4 text-label font-bold text-terracotta transition hover:bg-danger-bg">
                            Hapus
                        </button>
                    @endif
                </div>
            @endforeach
            <button type="button" wire:click="addItem"
                class="inline-flex min-h-touch items-center gap-2 justify-self-start rounded-xl border-2 border-forest-600 px-4 text-label font-bold text-forest-700 transition hover:bg-success-bg">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                Tambah jenis
            </button>
        </div>
    </x-ui.panel>

    {{-- Foto --}}
    <x-ui.panel title="Foto wajib" description="Unggah 1–5 foto JPEG, PNG, atau WebP, maksimal 5 MB per file.">
        <div class="space-y-3">
            <label for="pickup-photos" class="sr-only">Foto sampah</label>
            <input id="pickup-photos" wire:model="photos" type="file"
                accept="image/jpeg,image/png,image/webp" multiple
                class="block min-h-touch w-full rounded-xl border-2 border-dashed border-border bg-warm-canvas p-4 text-body text-text-secondary transition hover:border-forest-600 focus:outline-none focus:ring-2 focus:ring-focus">
            @error('photos')
                <p class="flex items-center gap-1.5 text-body-sm text-terracotta">
                    <svg viewBox="0 0 24 24" class="size-4 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    {{ $message }}
                </p>
            @enderror
        </div>
    </x-ui.panel>

    <x-ui.button type="button" wire:click="submit" wire:loading.attr="disabled">
        <span wire:loading.remove>Kirim Pengajuan</span>
        <span wire:loading>Memproses...</span>
    </x-ui.button>
</section>
