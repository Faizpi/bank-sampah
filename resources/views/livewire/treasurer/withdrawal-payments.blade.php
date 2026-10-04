<x-slot:title>Pembayaran pencairan</x-slot:title>
<x-slot:date>{{ now()->translatedFormat('d F Y') }}</x-slot:date>
<x-slot:connectivity>Terhubung</x-slot:connectivity>

<section aria-labelledby="withdrawal-payments-title" class="grid gap-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-harvest-gold">Bendahara</p>
            <h1 id="withdrawal-payments-title" class="mt-2 text-h1 font-bold text-deep-green">Pembayaran Pencairan</h1>
            <p class="mt-3 text-body text-text-secondary">
                Pembayaran hanya untuk Bendahara atau petugas yang ditugaskan sebagai payer. Pilih pencairan yang ditugaskan kepada Anda; verifikasi penerima dan bukti wajib sebelum saldo keluar.
            </p>
        </div>
        <x-ui.mascot variant="12" bubble="Bayar tepat waktu!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    @if (session('success'))
        <x-ui.success-state title="Pembayaran berhasil" :description="session('success')" />
    @endif

    {{-- Withdrawal List --}}
    @forelse ($withdrawals as $withdrawal)
        <x-ui.panel :title="$withdrawal->request_number" :description="$withdrawal->customer?->name ?? 'Nasabah'">
            <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="space-y-1">
                    <p class="amount-tabular text-h2 font-extrabold text-deep-green">
                        Rp{{ number_format($withdrawal->amount, 0, ',', '.') }}
                    </p>
                    <p class="text-body-sm text-text-secondary">
                        {{ $withdrawal->pickup_date?->translatedFormat('d F Y') ?? 'Tanggal belum tersedia' }}
                        @if ($withdrawal->pickup_location)
                            · {{ $withdrawal->pickup_location }}
                        @endif
                    </p>
                </div>
                <button type="button" wire:click="select({{ $withdrawal->id }})"
                    class="inline-flex min-h-touch shrink-0 items-center justify-center gap-2 rounded-xl bg-harvest-gold px-5 text-label font-bold text-deep-green shadow-xs transition hover:opacity-90">
                    <svg viewBox="0 0 24 24" class="size-4" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <rect width="20" height="14" x="2" y="5" rx="2"/><path d="M2 10h20"/>
                    </svg>
                    Proses Pembayaran
                </button>
            </div>
        </x-ui.panel>
    @empty
        <div class="rounded-xl border border-border bg-surface p-8 text-center shadow-xs">
            <x-ui.mascot variant="5" class="mx-auto h-24 w-auto" />
            <p class="mt-3 text-label font-bold text-deep-green">Belum ada pencairan siap bayar</p>
            <p class="mt-1.5 text-body-sm text-text-secondary">Pencairan yang disetujui dan ditugaskan kepada Anda akan muncul di sini.</p>
        </div>
    @endforelse

    {{-- Verification Form --}}
    @if ($selectedWithdrawalId !== null)
        <x-ui.panel title="Verifikasi dan bukti pembayaran" description="Pastikan nama penerima dan nominal sesuai sebelum mengirim." state="success">
            <div class="grid gap-4">
                <x-ui.select wire:model="recipientVerification" label="Metode verifikasi penerima" name="recipientVerification"
                    :options="['kartu_nasabah' => 'Kartu nasabah', 'nomor_nasabah' => 'Nomor nasabah']" />
                <x-ui.input wire:model="recipientReference" label="Referensi verifikasi" name="recipientReference"
                    placeholder="Contoh nomor nasabah" />
                <div class="space-y-1.5">
                    <label for="payment-proof" class="block text-label font-semibold text-deep-green">Bukti pembayaran</label>
                    <input id="payment-proof" wire:model="proof" type="file"
                        accept="image/jpeg,image/png,image/webp,application/pdf"
                        class="block min-h-touch w-full rounded-xl border-2 border-dashed border-border bg-warm-canvas p-4 text-body text-text-secondary transition hover:border-harvest-gold focus:outline-none focus:ring-2 focus:ring-focus">
                    @error('proof')
                        <p class="text-body-sm text-terracotta">{{ $message }}</p>
                    @enderror
                </div>
                <x-ui.button type="button" wire:click="pay" wire:loading.attr="disabled">
                    <span wire:loading.remove>Konfirmasi Pembayaran</span>
                    <span wire:loading>Memproses...</span>
                </x-ui.button>
            </div>
        </x-ui.panel>
    @endif
</section>
