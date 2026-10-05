<x-slot:title>Laporan {{ $reportTypes[$reportType] ?? 'Tidak diizinkan' }}</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section class="space-y-6" aria-labelledby="reports-title">
    {{-- Page Header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Laporan transaksi</p>
            <h1 id="reports-title" class="mt-1 text-h1 font-bold text-deep-green">Laporan {{ $reportTypes[$reportType] ?? 'Tidak diizinkan' }}</h1>
            <p class="mt-2 text-body text-text-secondary">Pilih periode, lihat ringkasan, lalu unduh Excel.</p>
        </div>
        <x-ui.mascot variant="5" class="hidden h-20 w-auto sm:block" />
    </div>

    {{-- Metric Cards --}}
    <div class="grid gap-3 grid-cols-2 sm:grid-cols-3 lg:grid-cols-5">
        @foreach ($metricDefinitions as $metric)
            @php($isCurrency = $metric['format'] === 'currency')
            <div class="rounded-xl border {{ $isCurrency ? 'border-forest-600/30 bg-success-bg' : 'border-border bg-surface' }} p-4 shadow-xs">
                <p class="text-caption font-medium {{ $isCurrency ? 'text-forest-700' : 'text-text-secondary' }}">{{ $metric['label'] }}</p>
                <strong class="mt-2 block amount-tabular text-h2 font-bold {{ $isCurrency ? 'text-forest-700' : 'text-deep-green' }}">
                    @if ($metric['format'] === 'currency')
                        Rp {{ number_format((int) $metrics[$metric['key']], 0, ',', '.') }}
                    @elseif ($metric['format'] === 'weight')
                        {{ \App\Support\WeightFormatter::format($metrics[$metric['key']], fixedTwoDecimals: true) }} <span class="text-label font-semibold text-text-secondary">kg</span>
                    @else
                        {{ $metrics[$metric['key']] }}
                    @endif
                </strong>
            </div>
        @endforeach
    </div>

    {{-- Filter Form --}}
    <x-ui.panel title="Filter laporan" description="Gunakan pilihan cepat atau tentukan tanggal sendiri.">
        <div class="mb-4 flex flex-wrap justify-end gap-2" aria-label="Preset periode">
            <button type="button" wire:click="setPeriod('today')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'today' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Hari ini</button>
            <button type="button" wire:click="setPeriod('week')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'week' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Minggu ini</button>
            <button type="button" wire:click="setPeriod('month')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'month' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Per bulan</button>
            <button type="button" wire:click="setPeriod('custom')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'custom' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Rentang tanggal</button>
        </div>
        <form wire:submit="refreshReport" class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4" aria-label="Filter laporan">
            <x-ui.select wire:model="reportType" name="reportType" label="Jenis laporan" :options="$reportTypes" />
            @if ($period === 'month')
                <x-ui.select wire:model="month" name="month" label="Bulan" :options="$months" />
                <x-ui.select wire:model="year" name="year" label="Tahun" :options="$years" />
            @elseif ($period === 'custom')
                <x-ui.input wire:model="start" name="start" label="Mulai" type="date" />
                <x-ui.input wire:model="end" name="end" label="Hingga" type="date" />
            @else
                <div class="rounded-md border border-border bg-warm-canvas px-4 py-3 sm:col-span-2">
                    <p class="text-caption font-medium text-text-secondary">Periode aktif</p>
                    <p class="mt-1 text-body-sm font-semibold text-deep-green">{{ \Carbon\CarbonImmutable::parse($start)->translatedFormat('d F Y') }} – {{ \Carbon\CarbonImmutable::parse($end)->translatedFormat('d F Y') }}</p>
                </div>
            @endif
            <x-ui.select wire:model="serviceAreaId" name="serviceAreaId" label="Area pelayanan (opsional)" :options="$serviceAreas->all()"><option value="">Semua area</option></x-ui.select>
            <x-ui.select wire:model="status" name="status" label="Status (opsional)" :options="$statusOptions"><option value="">Semua status</option></x-ui.select>
            <x-ui.input wire:model="search" name="search" label="Nomor transaksi" placeholder="Contoh: STR-2026-001" />
            <x-ui.button type="submit" wire:loading.attr="disabled" class="justify-self-end lg:self-end">
                <span wire:loading.remove>Terapkan</span>
                <span wire:loading>Menerapkan...</span>
            </x-ui.button>
        </form>
    </x-ui.panel>

    {{-- Export --}}
    <x-ui.panel title="Ekspor Excel" description="Unduh data sesuai filter aktif untuk disimpan atau dibagikan secara internal.">
        <div class="flex justify-end">
            <x-ui.button wire:click="export" type="button" wire:loading.attr="disabled" class="min-w-[10.5rem] whitespace-nowrap px-5">
                <span wire:loading.remove wire:target="export" class="inline-flex w-full flex-nowrap items-center justify-center gap-2 whitespace-nowrap">
                    <svg viewBox="0 0 24 24" class="size-5 shrink-0" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <path d="M12 3v12m0 0 5-5m-5 5-5-5M5 19h14a2 2 0 0 0 2-2v-2M5 19a2 2 0 0 1-2-2v-2" />
                    </svg>
                    <span class="whitespace-nowrap">Unduh Excel</span>
                </span>
                <span wire:loading wire:target="export" class="inline-flex w-full flex-nowrap items-center justify-center whitespace-nowrap">Mengekspor...</span>
            </x-ui.button>
        </div>
    </x-ui.panel>

    <x-ui.panel title="Hasil laporan" description="Data yang tampil mengikuti hak akses dan filter yang dipilih.">
        <div class="grid gap-3 md:hidden">
            @forelse ($rows as $row)
                @php($isWeight = $row['value_format'] === 'weight')
                <article class="rounded-md border border-border bg-warm-canvas p-4">
                    <h3 class="text-label font-bold text-deep-green">{{ $row['reference'] }}</h3>
                    <dl class="mt-3 grid gap-2 text-body-sm">
                        <div><dt class="text-text-secondary">Waktu</dt><dd class="font-semibold text-deep-green">{{ $row['date'] }}</dd></div>
                        <div><dt class="text-text-secondary">Nasabah</dt><dd class="font-semibold text-deep-green">{{ $row['subject'] }}</dd></div>
                        <div><dt class="text-text-secondary">Detail</dt><dd class="text-deep-green">{{ $row['detail'] }}</dd></div>
                        <div><dt class="text-text-secondary">Status</dt><dd class="font-semibold text-deep-green">{{ \App\Support\StatusLabel::for($row['status']) }}</dd></div>
                        <div>
                            <dt class="text-text-secondary">{{ $isWeight ? 'Estimasi berat' : 'Nilai' }}</dt>
                            <dd class="amount-tabular font-semibold text-deep-green">
                                @if ($row['value'] === '' || $row['value'] === null)
                                    —
                                @elseif ($isWeight)
                                    {{ \App\Support\WeightFormatter::format($row['value'], fixedTwoDecimals: true) }} kg
                                @else
                                    Rp {{ number_format((int) $row['value'], 0, ',', '.') }}
                                @endif
                            </dd>
                        </div>
                    </dl>
                </article>
            @empty
                <x-ui.empty-state kind="no-results" title="Tidak ada hasil" description="Ubah periode atau hapus filter untuk melihat data lain." />
            @endforelse
        </div>
        <div class="hidden overflow-x-auto md:block">
            <table class="min-w-full text-left text-sm" aria-label="Hasil laporan">
                <thead class="border-b border-border text-caption text-text-secondary">
                    <tr>
                        <th class="px-3 py-2 font-semibold">Referensi</th>
                        <th class="px-3 py-2 font-semibold">Waktu</th>
                        <th class="px-3 py-2 font-semibold">Nasabah</th>
                        <th class="px-3 py-2 font-semibold">Detail</th>
                        <th class="px-3 py-2 font-semibold">Status</th>
                        <th class="px-3 py-2 text-right font-semibold">Nilai</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-border">
                    @forelse ($rows as $row)
                        @php($isWeight = $row['value_format'] === 'weight')
                        <tr>
                            <td class="whitespace-nowrap px-3 py-2 font-medium text-deep-green">{{ $row['reference'] }}</td>
                            <td class="whitespace-nowrap px-3 py-2 text-text-secondary">{{ $row['date'] }}</td>
                            <td class="px-3 py-2 text-deep-green">{{ $row['subject'] }}</td>
                            <td class="px-3 py-2 text-text-secondary">{{ $row['detail'] }}</td>
                            <td class="px-3 py-2 text-text-secondary">{{ \App\Support\StatusLabel::for($row['status']) }}</td>
                            <td class="px-3 py-2 text-right amount-tabular">
                                @if ($row['value'] === '' || $row['value'] === null)
                                    —
                                @elseif ($isWeight)
                                    {{ \App\Support\WeightFormatter::format($row['value'], fixedTwoDecimals: true) }} kg
                                @else
                                    Rp {{ number_format((int) $row['value'], 0, ',', '.') }}
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="6" class="px-3 py-6 text-center text-text-secondary">Tidak ada hasil. Ubah periode atau hapus filter.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-ui.panel>
</section>
