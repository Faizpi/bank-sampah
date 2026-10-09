@props(['wasteType', 'showEducation' => false])
<article id="jenis-{{ $wasteType->id }}" {{ $attributes->class(['scroll-mt-24 overflow-hidden rounded-2xl border border-border bg-surface']) }}>
    <x-public.waste-photo :waste-type="$wasteType" class="aspect-[4/3] w-full border-b border-border" />
    <div class="p-5 sm:p-6">
        <p class="text-caption font-semibold text-forest-600">{{ $wasteType->category->name }}</p>
        <h3 class="mt-1 break-words text-h3 font-bold text-deep-green">{{ $wasteType->name }}</h3>
        <p class="mt-2 text-body-sm text-text-secondary">{{ $wasteType->conditions->pluck('name')->join(', ') }}</p>
        <dl class="mt-4 space-y-2 border-t border-border pt-4 text-body-sm">
            @forelse ($wasteType->prices as $price)
                <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
                    <dt class="text-text-secondary">{{ $price->condition->name }}</dt>
                    <dd class="font-bold tabular-nums text-deep-green">Rp{{ number_format($price->price, 0, ',', '.') }}<span class="font-normal text-text-secondary"> / {{ $wasteType->unit->symbol }}</span></dd>
                </div>
            @empty
                <div><dt class="sr-only">Harga aktif</dt><dd class="text-text-secondary">Harga belum tersedia</dd></div>
            @endforelse
        </dl>
        @if ($showEducation)
            <p class="mt-4 text-caption text-text-secondary">Kode {{ $wasteType->code }} · {{ $wasteType->unit->name }} ({{ $wasteType->unit->symbol }})</p>
            @if ($wasteType->education_description)
                <div class="mt-4 border-t border-border pt-4">
                    <h4 class="text-label font-semibold text-forest-700">Panduan pemilahan</h4>
                    <p class="mt-2 text-body-sm leading-relaxed text-text-secondary">{{ $wasteType->education_description }}</p>
                </div>
            @endif
        @endif
    </div>
</article>
