<x-slot:title>Bukti pencairan</x-slot:title>
<x-slot:context>Riwayat warga</x-slot:context>

<section aria-labelledby="withdrawal-receipt-title" class="grid gap-6" data-print-area>
    <div>
        <p class="text-label text-forest-600">Bukti {{ $withdrawal->request_number }}</p>
        <h1 id="withdrawal-receipt-title" class="mt-2 text-h1 text-deep-green">Pencairan berhasil</h1>
        <p class="mt-3 text-body text-text-secondary">Bukti ini hanya dapat dilihat oleh pemilik pencairan yang sudah dibayar.</p>
    </div>

    <x-ui.success-state
        title="Pencairan berhasil"
        :reference="$withdrawal->request_number"
        :value="'Rp '.number_format($withdrawal->amount, 0, ',', '.')"
        :time="$withdrawal->paid_at?->translatedFormat('d F Y, H:i')"
        :status="$withdrawal->status->value"
    />

    <x-ui.panel title="Ringkasan pembayaran" description="Saldo keluar tercatat setelah pembayaran sah.">
        <dl class="grid gap-4 text-body md:grid-cols-2">
            <div><dt class="text-body-sm text-text-secondary">Nomor</dt><dd class="mt-1 font-semibold">{{ $withdrawal->request_number }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Nominal</dt><dd class="mt-1 font-semibold tabular-nums">Rp{{ number_format($withdrawal->amount, 0, ',', '.') }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Dibayar</dt><dd class="mt-1 font-semibold">{{ $withdrawal->paid_at?->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Verifikasi</dt><dd class="mt-1 font-semibold">{{ str_replace('_', ' ', $withdrawal->recipient_verification) }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Referensi penerima</dt><dd class="mt-1 font-semibold">{{ $withdrawal->recipient_reference }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Petugas pembayaran</dt><dd class="mt-1 font-semibold">{{ $withdrawal->payer?->name }}</dd></div>
        </dl>
    </x-ui.panel>

    <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between" data-print-hide>
        <p class="text-caption text-text-secondary">Butuh arsip? Cetak bukti ini atau simpan sebagai PDF dari dialog cetak.</p>
        <button type="button" data-print-button class="inline-flex min-h-touch items-center justify-center gap-2 rounded-md bg-forest-600 px-5 text-label font-bold text-white transition hover:bg-forest-700">
            <svg viewBox="0 0 24 24" class="size-5" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M6 9V3h12v6M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2M6 14h12v7H6z"/></svg>
            Cetak bukti
        </button>
    </div>
</section>
