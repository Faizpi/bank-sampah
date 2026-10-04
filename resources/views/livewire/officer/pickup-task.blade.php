<x-slot:title>Tugas penjemputan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section aria-labelledby="pickup-task-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Tugas {{ $pickup->request_number }}</p>
            <h1 id="pickup-task-title" class="mt-2 text-h1 font-bold text-deep-green">Penjemputan Lapangan</h1>
            <p class="mt-3 text-body text-text-secondary">
                <svg viewBox="0 0 24 24" class="mr-1 inline size-4 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/></svg>
                {{ $pickup->address }}
            </p>
        </div>
        <x-ui.mascot variant="8" bubble="Menuju lokasi!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    {{-- Status Panel --}}
    <x-ui.panel title="Status tugas" description="Urutan status harus diikuti. Estimasi tidak digunakan untuk saldo.">
        <div class="flex items-center gap-3">
            <span class="inline-flex items-center gap-1.5 rounded-full border border-info-bg bg-info-bg px-4 py-1.5 text-label font-bold text-sky-blue">
                <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                {{ ucwords(str_replace('_', ' ', $pickup->status->value)) }}
            </span>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            @if ($pickup->status->value === 'dijadwalkan')
                <button type="button" wire:click="begin" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-sky-blue px-5 text-label font-bold text-white transition hover:opacity-90 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M3 11l19-9-9 19-2-8-8-2Z"/></svg>
                    Menuju Lokasi
                </button>
            @endif
            @if ($pickup->status->value === 'menuju_lokasi')
                <button type="button" wire:click="markPickedUp" wire:loading.attr="disabled"
                    class="inline-flex min-h-touch items-center justify-center gap-2 rounded-xl bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700 disabled:cursor-wait disabled:opacity-60">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M20 7H4a2 2 0 0 0-2 2v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2V9a2 2 0 0 0-2-2Z"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/></svg>
                    Tandai Dijemput
                </button>
            @endif
        </div>
    </x-ui.panel>

    {{-- Actual Weighing --}}
    @if ($canComplete)
        <x-ui.panel title="Timbang aktual" description="Berat aktual ini menjadi sumber setoran dan saldo." state="success">
            <div class="grid gap-4">
                @foreach ($actualItems as $index => $item)
                    <div wire:key="actual-item-{{ $index }}" class="grid gap-3 rounded-xl border border-border bg-warm-canvas p-4 md:grid-cols-3">
                        <x-ui.select wire:model="actualItems.{{ $index }}.waste_type_id"
                            label="Jenis sampah" name="actualItems.{{ $index }}.waste_type_id"
                            :options="$types->pluck('name', 'id')->all()" />
                        <x-ui.select wire:model="actualItems.{{ $index }}.condition_id"
                            label="Kondisi" name="actualItems.{{ $index }}.condition_id"
                            :options="$conditions->pluck('name', 'id')->all()" />
                        <x-ui.input wire:model="actualItems.{{ $index }}.weight_kg"
                            label="Berat aktual (kg)" name="actualItems.{{ $index }}.weight_kg"
                            inputmode="decimal" />
                    </div>
                @endforeach

                <div class="flex flex-col gap-3 sm:flex-row">
                    <button type="button"
                        wire:click="$set('actualItems', [...$actualItems, ['waste_type_id' => '', 'condition_id' => '', 'weight_kg' => '']])"
                        class="inline-flex min-h-touch items-center gap-2 justify-self-start rounded-xl border-2 border-forest-600 px-4 text-label font-bold text-forest-700 transition hover:bg-success-bg">
                        <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 5v14M5 12h14"/></svg>
                        Tambah Detail Timbang
                    </button>
                    <x-ui.button type="button" wire:click="complete" wire:loading.attr="disabled">
                        <span wire:loading.remove>Finalkan Setoran Aktual</span>
                        <span wire:loading>Memproses...</span>
                    </x-ui.button>
                </div>
            </div>
        </x-ui.panel>
    @endif

    {{-- Status History --}}
    <x-ui.panel title="Riwayat status" description="Perubahan status tercatat untuk penelusuran.">
        <x-ui.timeline :steps="$pickup->statusHistory->map(fn ($history): array => [
            'title'  => ucwords(str_replace('_', ' ', $history->new_status)),
            'note'   => $history->reason ?? '',
            'time'   => $history->occurred_at->translatedFormat('d M Y, H:i'),
            'status' => match($history->new_status) {
                'selesai'    => 'success',
                'ditolak', 'dibatalkan' => 'error',
                default      => 'in_progress',
            },
        ])->all()" />
    </x-ui.panel>
</section>

