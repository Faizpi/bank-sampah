@props(['kind' => 'no-data', 'title', 'description', 'actionLabel' => null, 'actionHref' => null])
@php
    $kinds = ['no-data' => 'inbox', 'no-results' => 'search-x'];
    $resolvedKind = array_key_exists($kind, $kinds) ? $kind : 'no-data';
@endphp

<section data-empty-kind="{{ $resolvedKind }}" {{ $attributes->class('py-8 text-center') }}>
    <svg data-lucide="{{ $kinds[$resolvedKind] }}" viewBox="0 0 24 24" class="mx-auto size-10 text-text-secondary"
        fill="none" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"
        aria-hidden="true">
        @if ($resolvedKind === 'no-results')
            <circle cx="11" cy="11" r="8" />
        <path d="m21 21-4.3-4.3M8 8l6 6m0-6-6 6" />@else
        <path d="M4 4h16v16H4zM4 13h4l2 3h4l2-3h4" />@endif
    </svg>
    <h2 class="mt-4 text-title text-deep-green">{{ $title }}</h2>
    <p class="mx-auto mt-2 max-w-lg text-body text-text-secondary">{{ $description }}</p>
    @if ($actionLabel && $actionHref)
        <a data-empty-action href="{{ $actionHref }}"
            class="mt-5 inline-flex min-h-touch items-center justify-center rounded-md bg-forest-600 px-5 text-label text-white transition duration-180 ease-standard hover:bg-forest-700 active:translate-y-px">{{ $actionLabel }}</a>
    @endif
</section>