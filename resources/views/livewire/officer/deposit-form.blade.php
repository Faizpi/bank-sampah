<x-slot:title>Setoran baru</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section aria-labelledby="deposit-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Operasional Petugas</p>
            <h1 id="deposit-title" class="mt-2 text-h1 font-bold text-deep-green">Setoran Baru</h1>
            <p class="mt-3 text-body text-text-secondary">
                Tambahkan berat aktual. Harga dan subtotal selalu dihitung ulang server saat finalisasi.
            </p>
        </div>
        <x-ui.mascot variant="11" bubble="Timbang dengan akurat!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <div role="status" class="flex items-center gap-3 rounded-xl border border-forest-600 bg-success-bg px-4 py-3.5 text-body text-deep-green">
            <svg viewBox="0 0 24 24" class="size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/>
            </svg>
            {{ session('success') }}
        </div>
    @endif

    <x-ui.panel title="Item setoran" description="Berat dalam kilogram dengan maksimal tiga angka desimal.">
        <div class="space-y-3">
            @forelse ($items as $index => $item)
                <div class="grid gap-3 rounded-xl border border-border bg-warm-canvas p-4 md:grid-cols-[1fr_1fr_1fr_auto] md:items-end"
                    wire:key="deposit-item-{{ $index }}">
                    <x-ui.select name="items.{{ $index }}.waste_type_id" label="Jenis sampah"
                        wire:model="items.{{ $index }}.waste_type_id"
                        :error="$errors->first('items.'.$index.'.waste_type_id')">
                        <option value="">Pilih jenis</option>
                        @foreach ($types as $type)
                            <option value="{{ $type->id }}">{{ $type->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.select name="items.{{ $index }}.condition_id" label="Kondisi"
                        wire:model="items.{{ $index }}.condition_id"
                        :error="$errors->first('items.'.$index.'.condition_id')">
                        <option value="">Pilih kondisi</option>
                        @foreach ($conditions as $condition)
                            <option value="{{ $condition->id }}">{{ $condition->name }}</option>
                        @endforeach
                    </x-ui.select>

                    <x-ui.input name="items.{{ $index }}.weight_kg" label="Berat (kg)"
                        inputmode="decimal" wire:model="items.{{ $index }}.weight_kg"
                        :error="$errors->first('items.'.$index.'.weight_kg')" />

                    <button type="button" wire:click="removeItem({{ $index }})"
                        class="inline-flex min-h-touch items-center justify-center rounded-xl border-2 border-terracotta px-4 text-label font-bold text-terracotta transition hover:bg-danger-bg">
                        Hapus
                    </button>
                </div>
            @empty
                <div class="rounded-xl border border-dashed border-border bg-warm-canvas p-6 text-center">
                    <x-ui.mascot variant="9" class="mx-auto h-16 w-auto" />
                    <p class="mt-2 text-label font-semibold text-deep-green">Belum ada item</p>
                    <p class="mt-1 text-caption text-text-secondary">Tambahkan minimal satu jenis sampah.</p>
                </div>
            @endforelse

            <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                <x-ui.button type="button" wire:click="addItem" variant="secondary">
                    <svg viewBox="0 0 24 24" class="mr-2 size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                    Tambah Item
                </x-ui.button>
                <x-ui.button type="button" wire:click="saveDraft" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="saveDraft">Simpan Draf</span>
                    <span wire:loading wire:target="saveDraft">Menyimpan...</span>
                </x-ui.button>
                <x-ui.button type="button" wire:click="finalize" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="finalize">Finalisasi Setoran</span>
                    <span wire:loading wire:target="finalize">Memproses...</span>
                </x-ui.button>
            </div>
        </div>
    </x-ui.panel>
</section>

