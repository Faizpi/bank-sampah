<x-slot:title>Bukti sembako</x-slot:title>
<x-slot:context>Riwayat warga</x-slot:context>

<section aria-labelledby="grocery-receipt-title" class="grid gap-6">
    <div>
        <p class="text-label text-forest-600">Bukti {{ $redemption->request_number }}</p>
        <h1 id="grocery-receipt-title" class="mt-2 text-h1 text-deep-green">Penyerahan berhasil</h1>
        <p class="mt-3 text-body text-text-secondary">Bukti privat ini hanya dapat dilihat pemilik penukaran dan pihak berwenang dalam cakupan data.</p>
    </div>

    <x-ui.success-state
        title="Penyerahan berhasil"
        :reference="$redemption->request_number"
        :value="'Rp '.number_format($redemption->value_snapshot, 0, ',', '.')"
        :time="$redemption->handed_over_at?->translatedFormat('d F Y, H:i')"
        :status="$redemption->status->value"
    />

    <x-ui.panel title="Ringkasan penyerahan" description="Saldo keluar dicatat setelah serah-terima yang sah.">
        <dl class="grid gap-4 text-body md:grid-cols-2">
            <div><dt class="text-body-sm text-text-secondary">Nomor</dt><dd class="mt-1 font-semibold">{{ $redemption->request_number }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Paket</dt><dd class="mt-1 font-semibold">{{ $redemption->package_snapshot['name'] ?? 'Paket sembako' }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Nilai saat pengajuan</dt><dd class="mt-1 font-semibold tabular-nums">Rp{{ number_format($redemption->value_snapshot, 0, ',', '.') }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Diserahkan</dt><dd class="mt-1 font-semibold">{{ $redemption->handed_over_at?->format('d M Y H:i') }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Verifikasi</dt><dd class="mt-1 font-semibold">{{ str_replace('_', ' ', (string) $redemption->recipient_verification) }}</dd></div>
            <div><dt class="text-body-sm text-text-secondary">Petugas</dt><dd class="mt-1 font-semibold">{{ $redemption->handoverActor?->name ?? 'Petugas' }}</dd></div>
        </dl>
    </x-ui.panel>

    @if ($redemption->proofMedia)
        <div class="flex justify-end">
            <a href="{{ route('grocery.proof', $redemption->proofMedia) }}" class="inline-flex min-h-touch items-center justify-center rounded-md border border-forest-600 px-5 text-label text-forest-700">Unduh bukti penyerahan</a>
        </div>
    @endif
</section>
