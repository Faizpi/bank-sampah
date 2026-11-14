<x-slot:title>{{ $surface === 'treasurer' ? 'Riwayat pencairan' : 'Laporan '.($reportTypes[$reportType] ?? 'Tidak diizinkan') }}</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity><x-ui.connectivity-status /></x-slot:connectivity>

<section class="min-w-0 space-y-6" aria-labelledby="reports-title">
    <x-ui.page-heading id="reports-title" :title="$surface === 'treasurer' ? 'Riwayat pencairan' : 'Laporan '.($reportTypes[$reportType] ?? 'Tidak diizinkan')" description="Pilih periode, lihat ringkasan, lalu unduh Excel." mascot="5" />
    @if ($surface === 'treasurer' && $digitalAvailable)
        <div role="group" aria-label="Metode pencairan" class="grid grid-cols-2 gap-3">
            <x-ui.button :variant="! $isDigital ? 'primary' : 'secondary'" wire:click="selectMethod('tunai')" :aria-pressed="! $isDigital ? 'true' : 'false'" wire:loading.attr="disabled" wire:target="selectMethod"><x-public.icon name="banknote" />Tunai</x-ui.button>
            <x-ui.button :variant="$isDigital ? 'primary' : 'secondary'" wire:click="selectMethod('digital')" :aria-pressed="$isDigital ? 'true' : 'false'" wire:loading.attr="disabled" wire:target="selectMethod"><x-public.icon name="wallet-cards" />Digital (simulasi)</x-ui.button>
        </div>
    @endif
    @if ($isDigital)<p role="note" class="rounded-lg border border-border bg-warning-bg p-4">Simulasi Xendit (tanpa pengiriman uang riil).</p>@elseif ($surface === 'treasurer')<p class="text-body-sm text-text-secondary">Pembayaran tunai yang Anda catat.</p>@endif

    {{-- Metric Cards --}}
    <div @class(['grid grid-cols-2 gap-3', 'sm:grid-cols-3' => count($metricDefinitions) !== 4, 'lg:grid-cols-5' => count($metricDefinitions) === 5, 'lg:grid-cols-4' => count($metricDefinitions) === 4])>
        @foreach ($metricDefinitions as $metric)
            @php($isCurrency = $metric['format'] === 'currency')
            <div class="rounded-xl border {{ $isCurrency ? 'border-forest-600/30 bg-success-bg' : 'border-border bg-surface' }} p-4 shadow-xs">
                <p class="text-caption font-medium {{ $isCurrency ? 'text-forest-700' : 'text-text-secondary' }}">{{ $metric['label'] }}</p>
                <strong class="mt-2 block min-w-0 break-words [overflow-wrap:anywhere] amount-tabular text-h2 font-bold {{ $isCurrency ? 'text-forest-700' : 'text-deep-green' }}">
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
    @if ($errors->any())
        <div role="alert" class="rounded-xl border border-terracotta bg-danger-bg p-4">
            <p class="font-bold">Periksa filter laporan</p>
            @foreach ($errors->all() as $message)<p>{{ $message }}</p>@endforeach
        </div>
    @endif
    <x-ui.panel title="Filter laporan">
        <div class="mb-5 grid grid-cols-2 gap-2 sm:grid-cols-4" role="group" aria-label="Preset periode">
            <button type="button" wire:click="setPeriod('today')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'today' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Hari ini</button>
            <button type="button" wire:click="setPeriod('week')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'week' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Minggu ini</button>
            <button type="button" wire:click="setPeriod('month')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'month' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Per bulan</button>
            <button type="button" wire:click="setPeriod('custom')" class="min-h-touch rounded-md border px-4 text-body-sm font-semibold {{ $period === 'custom' ? 'border-forest-600 bg-success-bg text-forest-700' : 'border-border bg-surface text-deep-green hover:border-forest-600 hover:bg-success-bg' }}">Rentang tanggal</button>
        </div>
        <form wire:submit="refreshReport" class="grid min-w-0 gap-4 sm:grid-cols-2" aria-label="Filter laporan">
            @if ($surface === 'backoffice')
            <x-ui.select wire:model.live="reportType" name="reportType" label="Jenis laporan" :options="$reportTypes" :error="$errors->first('reportType')" />
            @endif
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
            @if ($surface === 'backoffice')
            <x-ui.select wire:model="serviceAreaId" name="serviceAreaId" label="Area pelayanan (opsional)" :options="$serviceAreas->all()" :error="$errors->first('serviceAreaId')"><option value="">Semua area</option></x-ui.select>
            @endif
            @if (count($statusOptions) > 1)
            <x-ui.select wire:model="status" name="status" label="Status (opsional)" :options="$statusOptions" :error="$errors->first('status')"><option value="">Semua status</option></x-ui.select>
            @endif
            <x-ui.input wire:model="search" name="search" label="Nomor transaksi" placeholder="Cari nomor transaksi" />
            <div class="flex justify-end sm:col-span-2">
                <x-ui.button type="submit" wire:loading.attr="disabled" wire:target="refreshReport" class="w-full sm:w-auto">
                    <span wire:loading.remove wire:target="refreshReport">Terapkan</span>
                    <span wire:loading wire:target="refreshReport">Menerapkan...</span>
                </x-ui.button>
            </div>
        </form>
    </x-ui.panel>

    {{-- Export --}}
    <x-ui.panel title="Unduh laporan">
        @error('export')
            <div role="alert" class="mb-4 rounded-md border border-terracotta bg-danger-bg p-4 text-body-sm text-text-primary">
                <p class="font-semibold text-deep-green">Ekspor tidak dapat dibuat</p>
                <p class="mt-1">{{ $message }}</p>
            </div>
        @enderror
        <div class="flex justify-end">
            <x-ui.button wire:click="export" type="button" wire:loading.attr="disabled" wire:target="export" class="min-w-[10.5rem] whitespace-nowrap px-5">
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

    <x-ui.panel title="Hasil laporan">
        <p wire:loading wire:target="refreshReport,setPeriod" role="status" aria-live="polite" class="mb-4 text-body-sm font-semibold text-text-secondary">Memuat laporan...</p>
        <div wire:loading.grid wire:target="refreshReport,setPeriod" class="grid gap-3" aria-hidden="true">
            @for ($item = 0; $item < 3; $item++)
                <div class="rounded-md border border-border bg-warm-canvas p-4">
                    <div class="h-5 w-40 rounded-md bg-disabled-bg"></div>
                    <div class="mt-3 h-4 w-64 max-w-full rounded-md bg-disabled-bg"></div>
                    <div class="mt-2 h-4 w-48 max-w-full rounded-md bg-disabled-bg"></div>
                </div>
            @endfor
        </div>
        <div wire:loading.remove wire:target="refreshReport,setPeriod">
        <div class="grid gap-3 lg:hidden">
            @forelse ($rows as $row)
                @php($isWeight = $row['value_format'] === 'weight')
                <article class="rounded-md border border-border bg-warm-canvas p-4">
                    <h3 class="text-label font-bold text-deep-green">{{ $row['reference'] }}</h3>
                    <dl class="mt-3 grid gap-2 text-body-sm">
                        <div><dt class="text-text-secondary">Waktu</dt><dd class="font-semibold text-deep-green">{{ $row['date'] }}</dd></div>
                        <div><dt class="text-text-secondary">Nasabah</dt><dd class="font-semibold text-deep-green">{{ $row['subject'] }}</dd></div>
                        <div><dt class="text-text-secondary">Detail</dt><dd class="flex flex-wrap items-center gap-2 text-deep-green">@if (filled($row['channel_logo'] ?? null))<img src="{{ asset($row['channel_logo']) }}" alt="" data-channel-logo class="h-6 w-14 shrink-0 object-contain object-left">@endif<span>{{ $row['detail'] }}</span></dd></div>
                        <div><dt class="text-text-secondary">Status</dt><dd class="font-semibold text-deep-green">{{ $row['status_label'] ?? \App\Support\StatusLabel::for($row['status']) }}</dd></div>
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
                    @if (filled($row['diagnostic'] ?? null))<details class="mt-3 border-t border-border pt-2"><summary class="min-h-touch cursor-pointer font-semibold">Informasi pengajuan</summary><p class="break-words py-2 text-body-sm">{{ $row['diagnostic'] }}</p></details>@endif
                </article>
            @empty
                <x-ui.empty-state kind="no-results" title="Tidak ada hasil" description="Ubah periode atau hapus filter untuk melihat data lain." />
            @endforelse
        </div>
        <div class="hidden min-w-0 overflow-x-auto lg:block">
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
                            <td class="px-3 py-2 text-text-secondary"><div class="flex flex-wrap items-center gap-2">@if (filled($row['channel_logo'] ?? null))<img src="{{ asset($row['channel_logo']) }}" alt="" data-channel-logo class="h-6 w-14 shrink-0 object-contain object-left">@endif<span>{{ $row['detail'] }}</span></div></td>
                            <td class="px-3 py-2 text-text-secondary">{{ $row['status_label'] ?? \App\Support\StatusLabel::for($row['status']) }}@if (filled($row['diagnostic'] ?? null))<details class="mt-2"><summary class="min-h-touch cursor-pointer font-semibold">Informasi pengajuan</summary><p class="max-w-sm break-words py-2">{{ $row['diagnostic'] }}</p></details>@endif</td>
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
        </div>
    </x-ui.panel>
</section>
