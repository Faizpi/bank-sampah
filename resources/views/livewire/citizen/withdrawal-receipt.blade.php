<x-slot:title>Bukti pencairan</x-slot:title>
<x-slot:context>Riwayat warga</x-slot:context>

<section aria-labelledby="withdrawal-receipt-title" class="grid gap-6">
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

    @if ($withdrawal->proofMedia)
        <x-ui.panel title="Bukti pembayaran" description="Dokumen ini hanya tersedia melalui akses akun Anda.">
            @if (str_starts_with($withdrawal->proofMedia->mime_type, 'image/'))
                <a href="{{ route('withdrawal.proof', $withdrawal->proofMedia) }}" class="block overflow-hidden rounded-lg border border-border focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-forest-600">
                    <img src="{{ route('withdrawal.proof', $withdrawal->proofMedia) }}" alt="Bukti pembayaran pencairan {{ $withdrawal->request_number }}" class="max-h-96 w-full object-contain" />
                </a>
            @endif

            <div class="mt-4 flex justify-end">
                <a href="{{ route('withdrawal.proof', $withdrawal->proofMedia) }}" class="min-h-touch inline-flex items-center justify-center rounded-md border border-forest-600 px-5 text-label text-forest-700">Unduh bukti pembayaran</a>
            </div>
        </x-ui.panel>
    @endif
</section>
