<x-slot:title>Riwayat setoran</x-slot:title>
<x-slot:context>Transaksi Anda</x-slot:context>

<section aria-labelledby="history-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Riwayat Warga</p>
            <h1 id="history-title" class="mt-2 text-h1 font-bold text-deep-green">Riwayat Setoran</h1>
            <p class="mt-3 text-body text-text-secondary">Setoran dan koreksi hanya menampilkan data milik akun Anda.</p>
        </div>
        <x-ui.mascot variant="10" bubble="Semua tercatat rapi!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    {{-- Deposits --}}
    <x-ui.panel title="Setoran" description="Bukti final dan status transaksi.">
        <div class="divide-y divide-border">
            @forelse ($deposits as $deposit)
                <a href="{{ route('citizen.deposit-receipt', $deposit) }}"
                    class="flex items-center justify-between py-4 transition hover:opacity-80 first:pt-0 last:pb-0"
                    wire:key="deposit-{{ $deposit->id }}">
                    <div>
                        <p class="text-label font-semibold text-deep-green">{{ $deposit->deposit_number }}</p>
                        <p class="mt-0.5 text-caption text-text-secondary">
                            {{ $deposit->occurred_at->translatedFormat('d F Y, H:i') }}
                            @if ($deposit->total_weight_kg) · {{ $deposit->total_weight_kg }} kg @endif
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        <p class="amount-tabular text-title font-bold text-deep-green">
                            Rp {{ number_format((int) ($deposit->total_value ?? 0), 0, ',', '.') }}
                        </p>
                        <svg viewBox="0 0 24 24" class="size-4 text-text-secondary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>
            @empty
                <div class="py-8 text-center">
                    <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
                    <p class="mt-3 text-label font-semibold text-deep-green">Belum ada riwayat</p>
                    <p class="mt-1 text-caption text-text-secondary">Setoran final Anda akan muncul di sini.</p>
                </div>
            @endforelse
        </div>
        @if ($deposits->hasPages())
            <div class="mt-4">{{ $deposits->links() }}</div>
        @endif
    </x-ui.panel>

    {{-- Corrections --}}
    <x-ui.panel title="Koreksi" description="Nilai sebelum, sesudah, alasan, dan dampak saldo.">
        <div class="divide-y divide-border">
            @forelse ($corrections as $correction)
                <article class="py-4 first:pt-0 last:pb-0" wire:key="correction-{{ $correction->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <p class="text-label font-semibold text-deep-green">{{ $correction->correction_number }}</p>
                        <p class="amount-tabular text-title font-bold {{ $correction->delta_value < 0 ? 'text-terracotta' : 'text-forest-600' }}">
                            {{ $correction->delta_value < 0 ? '-' : '+' }}Rp {{ number_format(abs($correction->delta_value), 0, ',', '.') }}
                        </p>
                    </div>
                    <p class="mt-1.5 text-body-sm text-text-secondary">{{ $correction->reason }}</p>
                    <dl class="mt-3 grid gap-2 text-body-sm sm:grid-cols-2">
                        <div class="rounded-lg bg-warm-canvas px-3 py-2">
                            <dt class="text-caption text-text-secondary">Sebelum</dt>
                            <dd class="amount-tabular font-semibold text-deep-green">Rp {{ number_format((int) $correction->before_values['total_value'], 0, ',', '.') }}</dd>
                        </div>
                        <div class="rounded-lg bg-warm-canvas px-3 py-2">
                            <dt class="text-caption text-text-secondary">Sesudah</dt>
                            <dd class="amount-tabular font-semibold text-deep-green">Rp {{ number_format((int) $correction->after_values['total_value'], 0, ',', '.') }}</dd>
                        </div>
                    </dl>
                </article>
            @empty
                <p class="py-4 text-center text-body-sm text-text-secondary">Belum ada koreksi untuk akun Anda.</p>
            @endforelse
        </div>
        @if ($corrections->hasPages())
            <div class="mt-4">{{ $corrections->links() }}</div>
        @endif
    </x-ui.panel>
</section>
