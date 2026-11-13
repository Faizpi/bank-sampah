@php($channel = app(\App\Domain\Payouts\Services\PayoutChannels::class)->all()[$getState()] ?? null)
<div class="flex min-w-0 flex-wrap items-center gap-2 px-3 py-2">
    @if ($channel)
        <img src="{{ asset($channel['logo']) }}" alt="" data-channel-logo class="h-6 w-14 shrink-0 object-contain object-left">
        <span>{{ $channel['name'] }}</span>
    @else
        <span>Tujuan tidak tersedia</span>
    @endif
</div>
