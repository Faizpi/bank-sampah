@props([
    'eyebrow' => null,
    'title' => null,
    'description' => null,
    'align' => 'left',
    'level' => 2,
])

@php
    $resolvedAlign = in_array($align, ['left', 'center'], true) ? $align : 'left';
    $headingTag = in_array($level, [2, 3], true) ? "h{$level}" : 'h2';
    $headingClass = $level === 3 ? 'text-h3 lg:text-h3-lg' : 'text-h2 lg:text-h2-lg';
    $headingId = $attributes->get('id');
    $wrapperAttributes = $attributes->except('id');
@endphp

<div {{ $wrapperAttributes->class([
    'max-w-3xl',
    'text-left' => $resolvedAlign === 'left',
    'mx-auto text-center' => $resolvedAlign === 'center',
]) }}>
    @if ($eyebrow)
        <p class="mb-2 text-label font-semibold tracking-wide text-forest-600">{{ $eyebrow }}</p>
    @endif

    @if ($title)
        <{{ $headingTag }} @if ($headingId) id="{{ $headingId }}" @endif class="{{ $headingClass }} text-deep-green">{{ $title }}</{{ $headingTag }}>
    @endif

    @if ($description)
        <p class="mt-3 max-w-2xl text-body text-text-secondary {{ $resolvedAlign === 'center' ? 'mx-auto' : '' }}">{{ $description }}</p>
    @endif

    @isset($actions)
        <div class="mt-5 flex flex-wrap gap-2 {{ $resolvedAlign === 'center' ? 'justify-center' : 'justify-start' }}">
            {{ $actions }}
        </div>
    @endisset
</div>
