@props([
    'steps' => [],
    'currentStatus' => null,
    'history' => [],
    'terminalStep' => null,
    'completedStatuses' => [],
    'label' => 'Tahapan proses',
])

@php
    $stageDefinitions = collect($steps)->values();
    $historyItems = collect($history);
    $terminal = is_array($terminalStep) && $terminalStep !== [];
    $workflowComplete = in_array($currentStatus, $completedStatuses, true);
    $currentStageIndex = null;
    $lastReachedIndex = -1;

    foreach ($stageDefinitions as $stageIndex => $stage) {
        $statuses = $stage['statuses'] ?? [$stage['key'] ?? null];

        if ($currentStageIndex === null && in_array($currentStatus, $statuses, true)) {
            $currentStageIndex = $stageIndex;
        }

        foreach ($historyItems as $event) {
            $eventStatus = data_get($event, 'new_status') ?? data_get($event, 'status');
            if (in_array($eventStatus, $statuses, true)) {
                $lastReachedIndex = max($lastReachedIndex, $stageIndex);
            }
        }
    }

    if (!$terminal && $currentStageIndex !== null) {
        $lastReachedIndex = max($lastReachedIndex, $currentStageIndex);
    }

    if ($workflowComplete) {
        $currentStageIndex = null;
        $lastReachedIndex = $stageDefinitions->count() - 1;
    }

    $totalSteps = $stageDefinitions->count() + ($terminal ? 1 : 0);
@endphp

<ol aria-label="{{ $label }}" {{ $attributes->class('space-y-0') }}>
    @foreach ($stageDefinitions as $stageIndex => $stage)
        @php
            $statuses = $stage['statuses'] ?? [$stage['key'] ?? null];
            $event = $historyItems->filter(function ($historyEvent) use ($statuses): bool {
                $eventStatus = data_get($historyEvent, 'new_status') ?? data_get($historyEvent, 'status');
                return in_array($eventStatus, $statuses, true);
            })->last();
            $state = $terminal
                ? ($stageIndex <= $lastReachedIndex ? 'complete' : 'upcoming')
                : ($workflowComplete
                    ? 'complete'
                    : ($stageIndex < $currentStageIndex ? 'complete' : ($stageIndex === $currentStageIndex ? 'current' : 'upcoming')));
            $icon = match ($state) {
                'complete' => 'circle-check',
                default => $stage['icon'] ?? 'circle-alert',
            };
            $eventTime = data_get($event, 'occurred_at');
            $eventTimeLabel = $eventTime ? \Illuminate\Support\Carbon::parse($eventTime)->translatedFormat('d M Y, H:i') : null;
            $eventNote = data_get($event, 'reason');
            $stepNumber = $stageIndex + 1;
        @endphp
        <li
            data-step-state="{{ $state }}"
            @if ($state === 'current') aria-current="step" @endif
            class="relative flex gap-3 pb-7 last:pb-0"
        >
            @if ($stepNumber < $totalSteps)
                <span
                    aria-hidden="true"
                    @class([
                        'absolute left-[17px] top-9 h-[calc(100%-1.25rem)] border-l-2',
                        'border-forest-600' => $state === 'complete',
                        'border-border' => $state !== 'complete',
                    ])
                ></span>
            @endif

            <span
                @class([
                    'relative z-content grid size-9 shrink-0 place-items-center rounded-md ring-1 ring-inset',
                    'bg-success-bg text-forest-700 ring-forest-600/20' => $state === 'complete',
                    'bg-deep-green text-surface ring-deep-green' => $state === 'current',
                    'bg-disabled-bg text-text-secondary ring-border' => $state === 'upcoming',
                ])
            >
                <x-public.icon :name="$icon" size="size-5" />
            </span>

            <div class="min-w-0 flex-1 pt-0.5">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="text-label font-bold text-deep-green">{{ $stage['title'] ?? 'Tahap proses' }}</p>
                    <span @class([
                        'text-caption font-semibold',
                        'text-forest-700' => $state === 'complete',
                        'text-deep-green' => $state === 'current',
                        'text-text-secondary' => $state === 'upcoming',
                    ])>
                        {{ match ($state) { 'complete' => 'Selesai', 'current' => 'Sedang berjalan', default => 'Belum dimulai' } }}
                    </span>
                </div>
                @if (!empty($stage['description']))
                    <p class="mt-1 text-body-sm text-text-secondary">{{ $stage['description'] }}</p>
                @endif
                @if ($eventTimeLabel)
                    <p class="mt-1 text-caption text-text-secondary">{{ $eventTimeLabel }}</p>
                @endif
                @if ($eventNote)
                    <p class="mt-1 text-body-sm text-text-primary">{{ $eventNote }}</p>
                @endif
            </div>
        </li>
    @endforeach

    @if ($terminal)
        @php
            $terminalEvent = $historyItems->filter(function ($historyEvent) use ($terminalStep): bool {
                $eventStatus = data_get($historyEvent, 'new_status') ?? data_get($historyEvent, 'status');
                return $eventStatus === ($terminalStep['status'] ?? null);
            })->last();
            $terminalTime = data_get($terminalEvent, 'occurred_at');
            $terminalTimeLabel = $terminalTime ? \Illuminate\Support\Carbon::parse($terminalTime)->translatedFormat('d M Y, H:i') : null;
        @endphp
        <li data-step-state="error" aria-current="step" class="relative flex gap-3 pb-7 last:pb-0">
            <span class="relative z-content grid size-9 shrink-0 place-items-center rounded-md bg-danger-bg text-terracotta ring-1 ring-inset ring-terracotta/20">
                <x-public.icon name="circle-alert" size="size-5" />
            </span>
            <div class="min-w-0 flex-1 pt-0.5">
                <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                    <p class="text-label font-bold text-deep-green">{{ $terminalStep['title'] ?? 'Proses dihentikan' }}</p>
                    <span class="text-caption font-semibold text-terracotta">Tidak dilanjutkan</span>
                </div>
                @if (!empty($terminalStep['description']))
                    <p class="mt-1 text-body-sm text-text-secondary">{{ $terminalStep['description'] }}</p>
                @endif
                @if ($terminalTimeLabel)
                    <p class="mt-1 text-caption text-text-secondary">{{ $terminalTimeLabel }}</p>
                @endif
            </div>
        </li>
    @endif
</ol>
