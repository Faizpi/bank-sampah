@props([
    'label',
    'amount',
    'helper' => null,
    'updatedAt' => null,
    'status' => 'normal',
])

<div {{ $attributes->class('rounded-lg border border-border bg-surface p-5 shadow-xs') }}>
    <p class="text-label text-text-secondary">{{ $label }}</p>
    <p @class([
        'mt-2 text-amount-lg amount-tabular tracking-[-0.02em]',
        'text-deep-green' => $status === 'normal',
        'text-terracotta' => $status === 'error',
    ])>
        {{ $amount }}
    </p>
    @if ($helper)
        <p class="mt-3 text-body-sm text-text-secondary">{{ $helper }}</p>
    @endif
    @if ($updatedAt)
        <p class="mt-1 text-caption text-text-secondary">Diperbarui {{ $updatedAt }}</p>
    @endif
</div>
