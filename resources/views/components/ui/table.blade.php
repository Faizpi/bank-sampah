@props([
    'caption',
    'columns' => [],
    'rows' => [],
    'filters' => [],
    'filterLabel' => 'Filter aktif',
    'sticky' => false,
    'mobileMode' => 'stack',
])

@php
    $safeUrl = static function (mixed $url): ?string {
        if (!is_string($url) || $url === '' || preg_match('/[\x00-\x20\x7F]/', $url) === 1 || preg_match('/%(?![0-9A-Fa-f]{2})/', $url) === 1 || str_contains($url, '\\')) return null;
        $decoded = rawurldecode($url);
        if (preg_match('/[\x00-\x1F\x7F]/', $decoded) === 1 || preg_match('/^\s/', $decoded) === 1 || str_contains($decoded, '\\') || str_starts_with($url, '//') || str_starts_with($decoded, '//')) return null;
        foreach ([$url, $decoded] as $candidate) {
            if (preg_match('/^[a-z][a-z0-9+.-]*:/i', $candidate) === 1) {
                $parts = parse_url($candidate);
                if (!is_array($parts) || !isset($parts['scheme'], $parts['host']) || !in_array(strtolower($parts['scheme']), ['http', 'https'], true) || $parts['host'] === '') return null;
            } elseif (str_contains($candidate, '://') || str_starts_with($candidate, ':')) return null;
        }
        return $url;
    };
    $sortValues = ['ascending', 'descending', 'none', 'other'];
    $columnKeys = array_values(array_filter(array_map(static fn (mixed $column): ?string => is_array($column) && isset($column['key']) && is_string($column['key']) ? $column['key'] : null, $columns)));
    $stackOnMobile = $mobileMode === 'stack';
@endphp

<section {{ $attributes->class('min-w-0') }}>
    @if ($filters !== [])
        <div aria-label="{{ $filterLabel }}" class="mb-4 flex flex-wrap items-center gap-2" role="group">
            @foreach ($filters as $filter)
                @php
                    $filterLabelText = is_array($filter) && isset($filter['label']) ? (string) $filter['label'] : '';
                    $removeHref = is_array($filter) ? $safeUrl($filter['removeHref'] ?? null) : null;
                @endphp
                <span class="inline-flex min-h-touch items-center gap-2 rounded-sm border border-border bg-surface px-3 text-body-sm text-deep-green">
                    <span>{{ $filterLabelText }}</span>
                    @if ($removeHref)
                        <a href="{{ $removeHref }}" aria-label="Hapus filter {{ $filterLabelText }}" class="inline-flex size-8 items-center justify-center rounded-sm hover:bg-success-bg focus-visible:bg-success-bg">
                            <svg data-lucide="x" viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 6 6 18M6 6l12 12"/></svg>
                        </a>
                    @endif
                </span>
            @endforeach
        </div>
    @endif

    <div class="min-w-0 overflow-hidden rounded-lg border border-border bg-surface">
        <table class="w-full border-collapse text-left text-body-sm">
            <caption class="px-4 py-3 text-left text-title text-deep-green">{{ $caption }}</caption>
            <thead class="{{ $sticky ? 'sticky top-0 z-sticky' : '' }} hidden bg-disabled-bg md:table-header-group">
                <tr>
                    @foreach ($columns as $column)
                        @php
                            $column = is_array($column) ? $column : [];
                            $key = isset($column['key']) ? (string) $column['key'] : '';
                            $label = isset($column['label']) ? (string) $column['label'] : '';
                            $sort = isset($column['sort']) && in_array($column['sort'], $sortValues, true) ? $column['sort'] : null;
                            $numeric = ($column['numeric'] ?? false) === true;
                        @endphp
                        <th scope="col" @if ($sort) aria-sort="{{ $sort }}" @endif class="border-t border-border px-4 py-3 text-label text-deep-green {{ $numeric ? 'text-right' : '' }}">
                            {{ $label }}
                        </th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    @php
                        $row = is_array($row) ? $row : [];
                        $rowReference = '';
                        foreach ($columnKeys as $candidateKey) {
                            if ($candidateKey !== 'select' && isset($row[$candidateKey]) && is_scalar($row[$candidateKey])) { $rowReference = (string) $row[$candidateKey]; break; }
                        }
                        $rowReference = $rowReference !== '' ? $rowReference : (string) ($row['id'] ?? '');
                    @endphp
                    <tr class="{{ $stackOnMobile ? 'block border-t border-border p-4 md:table-row md:border-0 md:p-0' : 'table-row' }}" @if ($stackOnMobile) data-mobile-row-stack @endif>
                        @foreach ($columns as $column)
                            @php
                                $column = is_array($column) ? $column : [];
                                $key = isset($column['key']) ? (string) $column['key'] : '';
                                $label = isset($column['label']) ? (string) $column['label'] : '';
                                $type = isset($column['type']) ? (string) $column['type'] : 'text';
                                $numeric = ($column['numeric'] ?? false) === true;
                                $value = $row[$key] ?? null;
                            @endphp
                            <td class="{{ $stackOnMobile ? 'flex min-w-0 items-start justify-between gap-4 py-2 md:table-cell md:border-t md:border-border md:px-4 md:py-3' : 'border-t border-border px-4 py-3' }} {{ $numeric ? 'text-right amount-tabular' : '' }}">
                                @if ($stackOnMobile)<span class="shrink-0 text-label text-text-secondary md:hidden">{{ $label }}</span>@endif
                                <span class="min-w-0 break-words [overflow-wrap:anywhere] {{ $numeric ? 'amount-tabular' : '' }}">
                                    @switch($type)
                                        @case('checkbox')
                                            <label class="inline-flex min-h-touch items-center gap-2">
                                                <input type="checkbox" name="selected[]" value="{{ $row['id'] ?? '' }}" aria-label="Pilih transaksi {{ $rowReference }}" class="size-5 rounded-sm border-border text-forest-600 focus:ring-focus">
                                            </label>
                                            @break
                                        @case('status')
                                            @php $status = is_array($value) ? $value : []; @endphp
                                            <x-ui.status-badge :status="$status['value'] ?? 'pending'">{{ $status['label'] ?? 'Menunggu' }}</x-ui.status-badge>
                                            @break
                                        @case('action')
                                            @php
                                                $action = is_array($value) ? $value : [];
                                                $actionLabel = isset($action['label']) ? (string) $action['label'] : 'Lihat detail';
                                                $actionHref = $safeUrl($action['href'] ?? null);
                                            @endphp
                                            @if ($actionHref)<a href="{{ $actionHref }}" aria-label="{{ $actionLabel }}" class="inline-flex min-h-touch items-center rounded-md px-3 text-label text-forest-600 underline decoration-transparent underline-offset-4 hover:decoration-current">{{ $actionLabel }}</a>@endif
                                            @break
                                        @default
                                            {{ is_scalar($value) ? (string) $value : '' }}
                                    @endswitch
                                </span>
                            </td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ max(1, count($columns)) }}" class="border-t border-border px-4 py-8 text-center text-body text-text-secondary">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</section>
