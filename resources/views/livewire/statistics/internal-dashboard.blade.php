<x-slot:title>Statistik internal</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section class="space-y-6" aria-labelledby="internal-statistics-title">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Pengawasan berbasis data</p>
            <h1 id="internal-statistics-title" class="mt-1 text-h1 font-bold text-deep-green">Statistik internal</h1>
            <p class="mt-2 text-body text-text-secondary">Data mengikuti cakupan akun dan metrik dengan jumlah subjek di bawah ambang privasi akan disamarkan.</p>
        </div>
        <x-ui.mascot variant="5" class="hidden h-20 w-auto sm:block" />
    </div>

    <x-ui.panel title="Filter statistik" description="Periode akhir bersifat eksklusif. Pilihan RT hanya memuat wilayah yang boleh Anda lihat.">
        <form wire:submit="refreshStatistics" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
            <x-ui.input wire:model="start" name="start" label="Mulai" type="date" :error="$errors->first('start')" />
            <x-ui.input wire:model="end" name="end" label="Sampai" type="date" :error="$errors->first('end')" />
            <x-ui.select wire:model="rtId" name="rtId" label="RT (opsional)" :error="$errors->first('rtId')">
                <option value="">Semua RT dalam cakupan</option>
                @foreach ($rts as $rt)
                    <option value="{{ $rt->id }}">{{ $rt->name }}</option>
                @endforeach
            </x-ui.select>
            <div class="self-end">
                <x-ui.button type="submit" wire:loading.attr="disabled">
                    <span wire:loading.remove wire:target="refreshStatistics">Terapkan Filter</span>
                    <span wire:loading wire:target="refreshStatistics">Memuat...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    @if (($statistics['suppressed'] ?? true) === true)
        <x-ui.panel title="Statistik disamarkan" description="Jumlah subjek pada filter ini belum memenuhi ambang privasi. Detail transaksi tidak ditampilkan." state="warning" />
    @else
        <div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
            @foreach ([
                ['key' => 'active_customers', 'label' => 'Nasabah aktif'],
                ['key' => 'deposit_count', 'label' => 'Setoran'],
                ['key' => 'total_weight_kg', 'label' => 'Total berat', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'plastic_weight_kg', 'label' => 'Berat plastik', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'target_progress_kg', 'label' => 'Progres target', 'suffix' => ' kg', 'format' => 'weight'],
                ['key' => 'mobile_service_count', 'label' => 'Layanan keliling'],
                ['key' => 'subject_count', 'label' => 'Subjek terukur'],
                ['key' => 'dominant_waste_type', 'label' => 'Jenis dominan'],
            ] as $metric)
                <article class="rounded-xl border border-border bg-surface p-4 shadow-xs">
                    <p class="text-caption font-semibold text-text-secondary">{{ $metric['label'] }}</p>
                    <p class="mt-2 text-h2 font-bold tabular-nums text-deep-green">{{ ($metric['format'] ?? null) === 'weight' ? \App\Support\WeightFormatter::format($statistics[$metric['key']] ?? null) : ($statistics[$metric['key']] ?? '—') }}{{ $metric['suffix'] ?? '' }}</p>
                </article>
            @endforeach
        </div>
    @endif
</section>
