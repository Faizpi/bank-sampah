@props([
    'title',
    'period',
    'unit',
    'type' => 'bar',
    'series' => [],
    'summary' => null,
    'state' => 'default',
])

@php
    $tones = [
        'forest' => 'bg-forest-600',
        'gold' => 'bg-harvest-gold',
        'sky' => 'bg-sky-blue',
        'terracotta' => 'bg-terracotta',
    ];
    $patterns = ['solid', 'diagonal', 'dotted', 'crosshatch'];
    $resolvedState = in_array($state, ['default', 'empty', 'unavailable'], true) ? $state : 'default';
    $resolvedType = in_array($type, ['bar', 'line', 'heatmap'], true) ? $type : 'bar';
    $normalizedSeries = [];
    foreach ($series as $item) {
        if (!is_array($item)) continue;
        $tone = isset($item['tone']) && array_key_exists($item['tone'], $tones) ? $item['tone'] : 'forest';
        $pattern = isset($item['pattern']) && in_array($item['pattern'], $patterns, true) ? $item['pattern'] : 'solid';
        $values = isset($item['values']) && is_array($item['values']) ? $item['values'] : [];
        $normalizedSeries[] = ['label' => (string) ($item['label'] ?? ''), 'tone' => $tone, 'pattern' => $pattern, 'values' => $values];
    }
    $resolvedState = $resolvedState === 'default' && $normalizedSeries === [] ? 'empty' : $resolvedState;
    $chartId = 'chart-'.Illuminate\Support\Str::uuid();
@endphp

<figure aria-labelledby="{{ $chartId }}-title" aria-describedby="{{ $chartId }}-context" data-chart-interactive="false" @if ($normalizedSeries !== []) data-chart-tone="{{ $normalizedSeries[0]['tone'] }}" data-chart-pattern="{{ $normalizedSeries[0]['pattern'] }}" @endif {{ $attributes->class('rounded-lg border border-border bg-surface p-4 sm:p-6') }}>
    <figcaption>
        <h2 id="{{ $chartId }}-title" class="text-title text-deep-green">{{ $title }}</h2>
        <p id="{{ $chartId }}-context" class="mt-1 text-body-sm text-text-secondary">{{ $period }} · Satuan: {{ $unit }}</p>
    </figcaption>

    @if ($resolvedState !== 'default')
        <div role="status" class="mt-6 border-t border-border pt-6 text-center">
            <svg data-lucide="{{ $resolvedState === 'empty' ? 'chart-no-axes-column' : 'triangle-alert' }}" viewBox="0 0 24 24" class="mx-auto size-10 text-text-secondary" fill="none" stroke="currentColor" stroke-width="1.75" aria-hidden="true">
                @if ($resolvedState === 'empty')<path d="M4 19V9M10 19V5M16 19v-7M22 19H2"/>@else<path d="M21 19H3L12 4l9 15Z"/><path d="M12 9v4m0 3h.01"/>@endif
            </svg>
            <p class="mt-3 text-label text-deep-green">{{ $resolvedState === 'empty' ? 'Belum ada data' : 'Data tidak tersedia' }}</p>
            <p class="mt-1 text-body-sm text-text-secondary">{{ $resolvedState === 'empty' ? 'Data untuk periode ini belum tersedia.' : 'Coba lagi nanti atau gunakan ringkasan data.' }}</p>
        </div>
    @else
        <div class="mt-5" aria-label="Legenda grafik">
            <ul class="flex flex-wrap gap-3">
                @foreach ($normalizedSeries as $item)
                    <li class="inline-flex items-center gap-2 text-body-sm text-text-primary">
                        <span aria-hidden="true" data-chart-tone="{{ $item['tone'] }}" data-chart-pattern="{{ $item['pattern'] }}" class="size-4 rounded-sm border border-deep-green {{ $tones[$item['tone']] }}"></span>
                        <span>{{ $item['label'] }} · pola {{ $item['pattern'] }}</span>
                    </li>
                @endforeach
            </ul>
        </div>

        <div class="mt-5 border-y border-border py-4" @if ($resolvedType === 'bar') data-bar-baseline="zero" @endif>
            <div class="mt-3 grid gap-3">
                @foreach ($normalizedSeries as $item)
                    @foreach ($item['values'] as $point)
                        @php
                            $point = is_array($point) ? $point : [];
                            $value = is_numeric($point['value'] ?? null) ? max(0, (float) $point['value']) : 0;
                            $width = min(100, $value > 0 ? max(4, $value / 20) : 0);
                        @endphp
                        <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center gap-3">
                            <div class="min-w-0">
                                <p class="truncate text-body-sm text-text-primary">{{ $point['label'] ?? '' }} · {{ $item['label'] }}</p>
                                <div aria-hidden="true" class="mt-1 h-3 overflow-hidden rounded-sm bg-disabled-bg">
                                    <span data-chart-tone="{{ $item['tone'] }}" data-chart-pattern="{{ $item['pattern'] }}" class="block h-full {{ $tones[$item['tone']] }}" style="width: {{ $width }}%"></span>
                                </div>
                            </div>
                            <span class="amount-tabular text-body-sm text-deep-green">{{ $point['formatted'] ?? $value.' '.$unit }}</span>
                        </div>
                    @endforeach
                @endforeach
            </div>
        </div>

    @endif

    @if ($summary)<p class="mt-4 text-body text-text-primary">{{ $summary }}</p>@endif

    <div class="mt-5 overflow-x-auto" aria-label="Ringkasan data grafik">
        <table class="w-full min-w-0 border-collapse text-body-sm">
            <caption class="pb-3 text-left text-label text-deep-green">Ringkasan data grafik</caption>
            <thead><tr><th scope="col" class="border-b border-border py-2 text-left">Kategori</th><th scope="col" class="border-b border-border py-2 text-left">Periode</th><th scope="col" class="border-b border-border py-2 text-right">Nilai</th></tr></thead>
            <tbody>
                @if ($resolvedState === 'default')
                    @foreach ($normalizedSeries as $item)
                        @foreach ($item['values'] as $point)
                            @php $point = is_array($point) ? $point : []; @endphp
                            <tr><th scope="row" class="border-b border-border py-2 text-left font-normal">{{ $item['label'] }}</th><td class="border-b border-border py-2">{{ $point['label'] ?? '' }}</td><td class="border-b border-border py-2 text-right amount-tabular">{{ $point['formatted'] ?? ($point['value'] ?? 0).' '.$unit }}</td></tr>
                        @endforeach
                    @endforeach
                @else
                    <tr><td colspan="3" class="border-b border-border py-3 text-left text-text-secondary">{{ $resolvedState === 'empty' ? 'Belum ada data untuk periode '.$period.'.' : 'Data untuk periode '.$period.' sedang tidak tersedia.' }}</td></tr>
                @endif
            </tbody>
        </table>
    </div>
</figure>
