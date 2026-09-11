@props(['type', 'subject', 'due', 'status' => 'pending', 'statusLabel', 'actionLabel', 'actionHref', 'group' => null, 'count' => null])

<div {{ $attributes->class('flex min-h-16 min-w-0 flex-col items-stretch gap-2 border-b border-border py-3 sm:flex-row sm:items-center sm:gap-3 sm:py-2') }}>
    <div class="min-w-0 flex-1">
        @if ($group && $count !== null)<p class="line-clamp-1 text-caption text-text-secondary" title="{{ $group }} · {{ $count }} tugas">{{ $group }} · {{ $count }} tugas</p>@endif
        <p class="line-clamp-2 text-label text-deep-green" title="{{ $subject }}" aria-label="{{ $type }} untuk {{ $subject }}">{{ $type }} · {{ $subject }}</p>
        <div class="mt-1 flex min-w-0 flex-wrap items-center gap-2">
            <span class="line-clamp-1 min-w-0 text-body-sm text-text-secondary" title="{{ $due }}">{{ $due }}</span>
            <x-ui.status-badge :status="$status" title="{{ $statusLabel }}">{{ $statusLabel }}</x-ui.status-badge>
        </div>
    </div>
    <a data-task-action href="{{ $actionHref }}" class="inline-flex min-h-touch w-full shrink-0 items-center justify-center rounded-md border border-border bg-surface px-5 text-label text-deep-green transition duration-180 ease-standard hover:border-forest-600 hover:bg-success-bg active:translate-y-px sm:w-auto">{{ $actionLabel }}</a>
</div>
