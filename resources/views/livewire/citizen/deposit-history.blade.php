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

    <nav aria-label="Jenis riwayat transaksi" class="grid grid-cols-3 gap-2 rounded-xl border border-border bg-surface p-2">
        <a href="{{ route('citizen.deposit-history') }}" aria-current="page" class="inline-flex min-h-touch items-center justify-center rounded-lg bg-success-bg px-3 text-center text-label font-bold text-forest-700">Setoran</a>
        <a href="{{ route('citizen.withdrawal-history') }}" class="inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary transition hover:bg-warm-canvas hover:text-deep-green">Pencairan</a>
        <a href="{{ route('citizen.grocery-history') }}" class="inline-flex min-h-touch items-center justify-center rounded-lg px-3 text-center text-label font-semibold text-text-secondary transition hover:bg-warm-canvas hover:text-deep-green">Sembako</a>
    </nav>

    {{-- Deposits --}}
    <x-ui.panel title="Setoran" description="Bukti final, kanal layanan, dan status transaksi.">
        <form class="grid gap-4 border-b border-border pb-4 sm:grid-cols-2 lg:grid-cols-5">
            <x-ui.input name="transactionNumber" label="Nomor transaksi" placeholder="Cari nomor setoran" wire:model.live.debounce.300ms="transactionNumber" />
            <x-ui.select name="status" label="Status" placeholder="Semua status" wire:model.live="status">
                @foreach ($statuses as $filterStatus)
                    <option value="{{ $filterStatus }}">{{ \App\Support\StatusLabel::for($filterStatus) }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.select name="method" label="Kanal setoran" placeholder="Semua kanal" wire:model.live="method">
                @foreach ($methods as $methodValue => $methodLabel)
                    <option value="{{ $methodValue }}">{{ $methodLabel }}</option>
                @endforeach
            </x-ui.select>
            <x-ui.input name="dateFrom" label="Dari tanggal" type="date" :error="$errors->first('dateFrom')" wire:model.live="dateFrom" />
            <x-ui.input name="dateUntil" label="Sampai tanggal" type="date" :error="$errors->first('dateUntil')" wire:model.live="dateUntil" />
        </form>

        <div class="divide-y divide-border pt-4">
            @forelse ($deposits as $deposit)
                <a href="{{ route('citizen.deposit-receipt', $deposit) }}"
                    class="flex min-w-0 flex-col gap-3 py-4 transition hover:opacity-80 first:pt-0 last:pb-0 sm:flex-row sm:items-center sm:justify-between"
                    wire:key="deposit-{{ $deposit->id }}">
                    <div class="min-w-0">
                        <div class="flex min-w-0 flex-wrap items-center gap-2">
                            <p class="min-w-0 break-words text-label font-semibold text-deep-green">{{ $deposit->deposit_number }}</p>
                            <x-ui.status-badge :status="match ($deposit->status) { \App\Domain\Deposits\Models\Deposit::STATUS_FINAL, \App\Domain\Deposits\Models\Deposit::STATUS_CORRECTED => 'success', \App\Domain\Deposits\Models\Deposit::STATUS_REJECTED, \App\Domain\Deposits\Models\Deposit::STATUS_REVERSED => 'error', \App\Domain\Deposits\Models\Deposit::STATUS_DRAFT => 'cancelled', default => 'pending' }">
                                {{ \App\Support\StatusLabel::for($deposit->status) }}
                            </x-ui.status-badge>
                        </div>
                        <p class="mt-1 text-body-sm text-text-secondary">
                            {{ $methods[$deposit->method] ?? \Illuminate\Support\Str::headline($deposit->method) }}
                            <span aria-hidden="true"> · </span>{{ $deposit->occurred_at->translatedFormat('d F Y, H:i') }}
                            @if ($deposit->total_weight_kg) <span aria-hidden="true"> · </span>{{ \App\Support\WeightFormatter::format($deposit->total_weight_kg) }} kg @endif
                        </p>
                    </div>
                    <div class="flex w-full flex-wrap items-center justify-between gap-2 sm:w-auto sm:flex-nowrap sm:justify-end">
                        <p class="amount-tabular break-words text-title font-bold text-deep-green">
                            Rp {{ number_format($deposit->effectiveTotalValue(), 0, ',', '.') }}
                        </p>
                        <svg viewBox="0 0 24 24" class="size-4 text-text-secondary" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="m9 18 6-6-6-6"/></svg>
                    </div>
                </a>
            @empty
                <div class="py-8 text-center">
                    <x-ui.mascot variant="9" class="mx-auto h-20 w-auto" />
                    <p class="mt-3 text-label font-semibold text-deep-green">Belum ada riwayat</p>
                    <p class="mt-1 text-caption text-text-secondary">
                        @if ($transactionNumber || $status || $method || $dateFrom || $dateUntil)
                            Tidak ada setoran yang sesuai dengan filter.
                        @else
                            Setoran final Anda akan muncul di sini.
                        @endif
                    </p>
                </div>
            @endforelse
        </div>
        @if ($deposits->hasPages())
            <div class="mt-4">{{ $deposits->links('components.ui.pagination') }}</div>
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
            <div class="mt-4">{{ $corrections->links('components.ui.pagination') }}</div>
        @endif
    </x-ui.panel>
</section>
