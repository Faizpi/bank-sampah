<x-slot:title>Bukti setoran</x-slot:title>
<x-slot:context>{{ $receipt['number'] }}</x-slot:context>

<section aria-labelledby="receipt-title" class="space-y-6">
    {{-- Page header --}}
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <p class="text-label font-semibold text-forest-600">Bukti Transaksi</p>
            <h1 id="receipt-title" class="mt-2 text-h1 font-bold text-deep-green">Setoran Selesai</h1>
            <p class="mt-3 text-body text-text-secondary">Simpan nomor bukti ini untuk verifikasi publik.</p>
        </div>
        <x-ui.mascot variant="2" bubble="Setoran berhasil tercatat!" bubblePosition="top" class="h-28 w-auto shrink-0" />
    </div>

    <x-ui.success-state
        title="Transaksi final"
        :reference="$receipt['number']"
        :value="'Rp '.number_format($receipt['value'], 0, ',', '.')"
        :time="\Illuminate\Support\Carbon::parse($receipt['date'])->translatedFormat('d F Y, H:i')"
        status="success"
    />

    <x-ui.panel title="Ringkasan" description="Detail yang aman untuk bukti warga.">
        <dl class="grid gap-4 sm:grid-cols-3">
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Nomor</dt>
                <dd class="mt-0.5 text-label font-bold text-deep-green">{{ $receipt['number'] }}</dd>
            </div>
            <div class="rounded-lg bg-warm-canvas px-3 py-2">
                <dt class="text-caption font-medium text-text-secondary">Total Berat</dt>
                <dd class="mt-0.5 amount-tabular text-label font-bold text-deep-green">{{ $receipt['weight_kg'] }} kg</dd>
            </div>
            <div class="rounded-lg bg-success-bg px-3 py-2">
                <dt class="text-caption font-medium text-forest-700">Total Nilai</dt>
                <dd class="mt-0.5 amount-tabular text-label font-bold text-forest-700">Rp {{ number_format($receipt['value'], 0, ',', '.') }}</dd>
            </div>
        </dl>
    </x-ui.panel>

    <x-ui.panel title="Item tersnapshot" description="Perubahan harga master tidak mengubah bukti ini.">
        <div class="divide-y divide-border">
            @foreach ($deposit->items as $item)
                <div class="flex flex-wrap items-center justify-between gap-3 py-4 first:pt-0 last:pb-0">
                    <div>
                        <p class="text-label font-semibold text-deep-green">{{ $item->waste_type_name }} · {{ $item->condition_name }}</p>
                        <p class="mt-0.5 text-body-sm text-text-secondary">{{ $item->weight_kg }} kg × Rp {{ number_format((int) $item->price_per_unit, 0, ',', '.') }}</p>
                    </div>
                    <p class="amount-tabular text-title font-bold text-deep-green">Rp {{ number_format((int) $item->subtotal, 0, ',', '.') }}</p>
                </div>
            @endforeach
        </div>
    </x-ui.panel>

    <x-ui.qr-display
        title="QR verifikasi setoran"
        context="Tunjukkan QR ini kepada petugas atau gunakan nomor bukti bila pemindaian tidak tersedia."
        :image-src="$qrDataUri"
        image-alt="QR verifikasi {{ $receipt['number'] }}"
        :masked-reference="substr($receipt['number'], 0, 8).'****'"
        :fallback-number="$receipt['number']"
        :print-href="route('citizen.deposit-receipt', $deposit)"
    />
</section>
