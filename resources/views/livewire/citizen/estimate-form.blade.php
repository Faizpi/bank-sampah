<x-slot:title>Estimasi nilai</x-slot:title>
<x-slot:context>Informasi sebelum setor</x-slot:context>

<section aria-labelledby="estimate-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Kalkulator Informasi</p>
            <h1 id="estimate-title" class="mt-2 text-h1 font-bold text-deep-green">Perkirakan nilai setoran.</h1>
            <p class="mt-3 text-body text-text-secondary">
                Hasil hanya panduan. Estimasi tidak membuat transaksi, menahan saldo, atau menjamin nilai akhir.
            </p>
        </div>
        <x-ui.mascot variant="7" bubble="Berapa kira-kira nilainya?" class="h-28 w-auto shrink-0" />
    </div>

    {{-- Calculator Form --}}
    <x-ui.panel title="Hitung estimasi" description="Pilih jenis, kondisi, dan masukkan perkiraan berat Anda.">
        <form wire:submit="calculate" class="grid gap-5">
            <x-ui.select wire:model="wasteTypeId" label="Jenis sampah" name="wasteTypeId" placeholder="Pilih jenis sampah"
                :options="$types->pluck('name', 'id')->all()"
                :error="$errors->first('wasteTypeId')" />

            <x-ui.select wire:model="conditionId" label="Kondisi" name="conditionId" placeholder="Pilih kondisi"
                :options="$conditions->pluck('name', 'id')->all()"
                :error="$errors->first('conditionId')" />

            <x-ui.input wire:model="weightKg" label="Perkiraan berat (kg)" name="weightKg"
                inputmode="decimal" placeholder="Contoh: 1.250"
                :error="$errors->first('weightKg')" />

            <div class="flex justify-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove>Hitung Estimasi</span>
                    <span wire:loading>Menghitung...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    {{-- Result --}}
    @if ($result)
        <div class="rounded-xl border border-forest-600/25 bg-success-bg p-5 sm:p-6" aria-live="polite" role="status">
            <div class="flex items-start gap-3">
                <svg viewBox="0 0 24 24" class="mt-0.5 size-5 shrink-0 text-forest-600" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                    <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10Z"/><path d="m9 12 2 2 4-4"/>
                </svg>
                <div class="flex-1">
                    <p class="text-label font-bold text-forest-700">Estimasi Informatif</p>
                    <p class="mt-2 amount-tabular text-amount-lg font-extrabold text-deep-green">
                        Rp{{ number_format($result['estimated_value'], 0, ',', '.') }}
                    </p>
                    <p class="mt-3 text-body-sm leading-relaxed text-text-primary">{{ $result['disclaimer'] }}</p>
                </div>
            </div>
        </div>
    @endif
</section>
