@props(['steps' => [], 'current' => null, 'label' => 'Linimasa status'])
@php
    $allowedStatuses = ['pending', 'in_progress', 'success', 'error', 'cancelled', 'expired'];
    $failed = false;
@endphp
<ol aria-label="{{ $label }}" {{ $attributes->class('space-y-0') }}>
    @foreach ($steps as $index => $step)
        @php
            $requested = $step['status'] ?? 'pending';
            $normalized = is_string($requested) && in_array($requested, $allowedStatuses, true) ? $requested : 'pending';
            $resolved = $failed && $normalized === 'success' ? 'pending' : $normalized;
            if ($resolved === 'error') $failed = true;
        @endphp
        <li @if ($current === $index) aria-current="step" @endif class="relative flex gap-3 pb-6 last:pb-0">
            @unless ($loop->last)<span aria-hidden="true" class="absolute left-3 top-7 h-[calc(100%-1.25rem)] border-l border-border"></span>@endunless
            <div class="relative z-content"><x-ui.status-badge :status="$resolved"><span class="sr-only">{{ str_replace('_', ' ', $resolved) }}</span></x-ui.status-badge></div>
            <div class="min-w-0 flex-1 pt-1">
                <p class="text-label text-deep-green">{{ $step['title'] ?? '' }}</p>
                @if (!empty($step['time']))<p class="mt-1 text-body-sm text-text-secondary">{{ $step['time'] }}</p>@endif
                @if (!empty($step['actor']))<p class="mt-1 break-words text-body-sm text-text-secondary">{{ $step['actor'] }}</p>@endif
                @if (!empty($step['note']))<p class="mt-1 break-words text-body text-text-primary">{{ $step['note'] }}</p>@endif
                @if (isset($step['before'], $step['after']))
                    <dl class="mt-3 grid gap-2 border-t border-border pt-3 sm:grid-cols-3">
                        <div><dt class="text-caption text-text-secondary">Sebelum</dt><dd class="amount-tabular text-body-sm">{{ $step['before'] }}</dd></div>
                        <div><dt class="text-caption text-text-secondary">Sesudah</dt><dd class="amount-tabular text-body-sm">{{ $step['after'] }}</dd></div>
                        @if (isset($step['balanceImpact']))<div><dt class="text-caption text-text-secondary">Dampak saldo</dt><dd class="amount-tabular text-body-sm">{{ $step['balanceImpact'] }}</dd></div>@endif
                    </dl>
                @endif
            </div>
        </li>
    @endforeach
</ol>
