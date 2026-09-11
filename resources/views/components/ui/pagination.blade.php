@php
    $label = 'Navigasi halaman';
    $currentPage = 1;
    $lastPage = 1;
    $from = 0;
    $to = 0;
    $total = 0;
    $previousUrl = null;
    $nextUrl = null;
    $pages = [];
    $isLivewirePagination = false;
    $pageName = 'page';

    if ($paginator instanceof \Illuminate\Contracts\Pagination\LengthAwarePaginator) {
        $currentPage = $paginator->currentPage();
        $lastPage = $paginator->lastPage();
        $from = $paginator->firstItem() ?? 0;
        $to = $paginator->lastItem() ?? 0;
        $total = $paginator->total();
        $previousUrl = $paginator->previousPageUrl();
        $nextUrl = $paginator->nextPageUrl();
        $pages = $paginator->linkCollection()->map(static fn (array $page): array => [
            'label' => $page['label'],
            'url' => $page['url'],
        ])->all();
        $isLivewirePagination = class_exists(\Livewire\Livewire::class);
        $pageName = $paginator->getPageName();
    }

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
    $normalizedLastPage = max(1, (int) $lastPage);
    $normalizedCurrentPage = min($normalizedLastPage, max(1, (int) $currentPage));
    $normalizedTotal = max(0, (int) $total);
    $rangeStart = max(1, (int) $from);
    $rangeEnd = max(1, (int) $to);
    $normalizedFrom = $normalizedTotal === 0 ? 0 : min($normalizedTotal, min($rangeStart, $rangeEnd));
    $normalizedTo = $normalizedTotal === 0 ? 0 : min($normalizedTotal, max($rangeStart, $rangeEnd));
    $previous = $normalizedCurrentPage > 1 ? $safeUrl($previousUrl) : null;
    $next = $normalizedCurrentPage < $normalizedLastPage ? $safeUrl($nextUrl) : null;
    $normalizedPages = [];
    foreach ($pages as $page) {
        if (!is_array($page)) continue;
        $number = filter_var($page['label'] ?? null, FILTER_VALIDATE_INT);
        if ($number === false || $number < 1 || $number > $normalizedLastPage || isset($normalizedPages[$number])) continue;
        $normalizedPages[$number] = ['label' => (string) $number, 'url' => $safeUrl($page['url'] ?? null)];
    }
    if (!isset($normalizedPages[$normalizedCurrentPage])) $normalizedPages[$normalizedCurrentPage] = ['label' => (string) $normalizedCurrentPage, 'url' => null];
    ksort($normalizedPages);
@endphp

<nav aria-label="{{ $label }}" class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
    <p aria-live="polite" class="text-caption text-text-secondary">Halaman {{ $normalizedCurrentPage }} dari {{ $normalizedLastPage }}</p>
    <div class="flex items-center justify-end gap-2 self-end sm:self-auto">
        @if ($previous)
            <button type="button" wire:click="previousPage('{{ $pageName }}')" rel="prev" class="inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-4 text-label text-deep-green hover:border-forest-600 hover:bg-success-bg">Sebelumnya</button>
        @else
            <span data-pagination-disabled aria-disabled="true" class="inline-flex min-h-touch cursor-not-allowed items-center justify-center rounded-md border border-border bg-disabled-bg px-4 text-label text-text-secondary">Sebelumnya</span>
        @endif

        @if ($normalizedPages !== [])
            <ol data-page-numbers class="hidden items-center gap-2 sm:flex">
                @foreach ($normalizedPages as $pageNumber => $page)
                    @php
                        $pageLabel = $page['label'];
                        $pageUrl = $page['url'];
                        $current = $pageNumber === $normalizedCurrentPage;
                    @endphp
                    <li>
                        @if ($current)
                            <span aria-current="page" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md bg-forest-600 px-3 text-label text-white">{{ $pageLabel }}</span>
                        @elseif ($pageUrl)
                            <button type="button" wire:click="gotoPage({{ $pageNumber }}, '{{ $pageName }}')" aria-label="Halaman {{ $pageLabel }}" class="inline-flex min-h-touch min-w-touch items-center justify-center rounded-md border border-border bg-surface px-3 text-label text-deep-green hover:bg-success-bg">{{ $pageLabel }}</button>
                        @endif
                    </li>
                @endforeach
            </ol>
        @endif

        @if ($next)
            <button type="button" wire:click="nextPage('{{ $pageName }}')" rel="next" class="inline-flex min-h-touch items-center justify-center rounded-md border border-border bg-surface px-4 text-label text-deep-green hover:border-forest-600 hover:bg-success-bg">Berikutnya</button>
        @else
            <span data-pagination-disabled aria-disabled="true" class="inline-flex min-h-touch cursor-not-allowed items-center justify-center rounded-md border border-border bg-disabled-bg px-4 text-label text-text-secondary">Berikutnya</span>
        @endif
    </div>
</nav>
